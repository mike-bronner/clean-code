# Code Style: Multiline Strings (HEREDOC)

## Standard

Two separate rules, and they are not the same rule at different sizes.

- **An embedded language uses a HEREDOC at any length.** HTML, XML, SQL, JSON,
  YAML, an INI or config block, markdown — anything that is another language
  written inside PHP belongs in a HEREDOC whatever its size, because the
  delimiter is what gives an editor a language to
  highlight. A quoted string is one flat run of characters to every tool that
  reads it. Enforced by `CleanCode.Strings.RequireHeredocForStructuredText`.
- **Any text longer than three lines uses a HEREDOC.** Past that the wrapping is
  no longer a concession to the line limit, it is a block of text, and a HEREDOC
  reads as the block it is.
- **A HEREDOC, never a NOWDOC.** The two differ only in the quotes around the
  opening identifier, and carrying both means a reader checks the delimiter
  before trusting what the body says. Enforced by
  `CleanCode.Strings.DisallowNowdoc`.

Lines of *text*, never lines of source. The distinction decides whether the rule
asks for something reachable. A sentence wrapped across four source lines is one
line of text, and a HEREDOC cannot express it: the body would sit on one line and
break the 120-character limit, or wrap and put real newlines into the value. So a
rule that counted the source reported what no fix could satisfy. Source layout is
already governed — the 100-character limit says how long a line may be, and the
leading-operator rule says where it breaks — and between them they produce
exactly the wrapping this rule used to flag.

The threshold is configurable through the `maximumLines` property.

```php
$string = <<<HTML
    <div>
        Hello, world!
    </div>
    HTML;
```

This matters most for inline SQL, where a multi-line HEREDOC reads as the query
it is:

```php
$sql = DB::statement(<<<SQL
    SELECT "Hello, world!"
    SQL);
```

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing PHPCS or Slevomat sniff enforces "multi-line string values must use
a HEREDOC". The closest,
[`Generic.Strings.UnnecessaryStringConcat`](https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/src/Standards/Generic/Sniffs/Strings/UnnecessaryStringConcatSniff.php),
flags concatenation of *adjacent* string literals for concision — it does not
flag a double- or single-quoted string that spans source lines, and it was
evaluated against this standard's test suite without matching it. The standard
is therefore enforced by the custom `CleanCode.Strings.MultilineStrings` sniff,
wired into the master `rules.xml` via the CleanCode standard
([#53](https://github.com/mike-bronner/phpcs-rules/issues/53)).

**This sniff owns length only.** It counts lines of text and never reads the
text for its shape. Embedded languages — HTML, XML, SQL, JSON, YAML, INI and other config
blocks, markdown — belong to `CleanCode.Strings.RequireHeredocForStructuredText`, which
owns them at any length. The two therefore hold disjoint slices, and no string
is reported twice.

- **Detection** — two shapes are flagged:
  - `CleanCode.Strings.MultilineStrings.QuotedString` — a single quoted string
    literal whose source spans more than one physical line (a double- or
    single-quoted string with a real newline between its quotes), reported at
    its opening quote.
  - `CleanCode.Strings.MultilineStrings.Concatenation` — a run of quoted strings
    joined with `.` whose **value** carries **more than `maximumLines` lines**
    (default 3), reported once at the first string operand of the chain. The
    count is of newlines in the text, so four source lines holding one sentence
    are compliant and one source line holding four `\n`-separated lines is not.
    Prose wrapped to stay inside the line limit is always compliant: that is the
    shape the line limit and the leading-operator rule require.
- **Not flagged** — constructs that are already compliant or are a single
  physical line:
  - **HEREDOC and NOWDOC bodies**, including PHP 7.3+ flexible (indented)
    closing markers — they tokenize as `T_START_HEREDOC` / `T_HEREDOC` /
    `T_END_HEREDOC`, never as string literals. A NOWDOC is left to
    `CleanCode.Strings.DisallowNowdoc`, which converts it.
  - **Single-line quoted strings** and **single-line concatenation**, including
    a single-line SQL statement passed as a plain string.
  - **A string whose only newline is the escape sequence `\n`** — that is one
    physical source line, not a multi-line string.
- **Auto-fixable:**
  - **QuotedString — Yes.** Always to a HEREDOC, whatever the source was quoted
    with. A double-quoted string already uses the escapes a HEREDOC resolves; a
    single-quoted string resolves only `\\` and `\x27`, so those come back to the
    characters they stand for and the whole body is then re-escaped for a
    HEREDOC, where a backslash and a `$` each mean something. The closing marker is emitted at column 0 so PHP strips no
    indentation and the string value is reproduced byte-for-byte — the fixer is
    behaviour-preserving, which the test suite proves by executing the before
    and after fixtures and asserting their values are identical. If a line of
    the string body would collide with the closing marker, the fix is withheld
    and the violation is reported detection-only.
  - **Concatenation — No (detection only).** Merging concatenated pieces into a
    single HEREDOC is a semantic rewrite: the interpolation mode, the whitespace
    between the pieces, and any non-string operands all have to be reasoned
    about, so a token-based fixer cannot do it safely. Collapsing the
    concatenation is left to the developer.

Ruleset-integration tests covering compliant code, per-line/column violation
reporting for both shapes, the HEREDOC auto-fix, byte-for-byte value
preservation, and the non-fixable (detection-only) guarantee for concatenation
live at `tests/Ruleset/MultilineStringsTest.php`.

## What remains code review

Nothing about *detecting* a multi-line quoted string or a line-wrapped
concatenation — that is fully machine-enforced, and the single-string case is
auto-fixed. Collapsing a flagged multi-line concatenation into one HEREDOC is
the manual work the sniff surfaces but does not perform.
