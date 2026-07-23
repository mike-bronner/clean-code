# Code Style: Multiline Strings (HEREDOC)

## Standard

- All multi-line strings must use HEREDOC (interpolating) or NOWDOC (literal,
  where no interpolation is needed) syntax rather than a quoted string that runs
  across source lines or a concatenation of quoted strings stitched together
  over several lines.

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
HEREDOC/NOWDOC". The closest,
[`Generic.Strings.UnnecessaryStringConcat`](https://github.com/PHPCSStandards/PHP_CodeSniffer/blob/master/src/Standards/Generic/Sniffs/Strings/UnnecessaryStringConcatSniff.php),
flags concatenation of *adjacent* string literals for concision — it does not
flag a double- or single-quoted string that spans source lines, and it was
evaluated against this standard's test suite without matching it. The standard
is therefore enforced by the custom `CleanCode.Strings.MultilineStrings` sniff,
wired into the master `rules.xml` via the CleanCode standard
([#53](https://github.com/mike-bronner/phpcs-rules/issues/53)).

- **Detection** — two shapes are flagged:
  - `CleanCode.Strings.MultilineStrings.QuotedString` — a single quoted string
    literal whose source spans more than one physical line (a double- or
    single-quoted string with a real newline between its quotes), reported at
    its opening quote.
  - `CleanCode.Strings.MultilineStrings.Concatenation` — a run of quoted strings
    joined with `.` that wraps across lines (`"SELECT *"\n . " FROM t"`),
    reported once at the first string operand of the chain.
- **Not flagged** — constructs that are already compliant or are a single
  physical line:
  - **HEREDOC and NOWDOC bodies**, including PHP 7.3+ flexible (indented)
    closing markers — they tokenize as `T_START_HEREDOC` / `T_HEREDOC` /
    `T_END_HEREDOC`, never as string literals.
  - **Single-line quoted strings** and **single-line concatenation**, including
    a single-line SQL statement passed as a plain string.
  - **A string whose only newline is the escape sequence `\n`** — that is one
    physical source line, not a multi-line string.
- **Auto-fixable:**
  - **QuotedString — Yes.** A double-quoted string is converted to a HEREDOC
    (interpolation preserved); a single-quoted string is converted to a NOWDOC
    (kept literal). The closing marker is emitted at column 0 so PHP strips no
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
reporting for both shapes, the HEREDOC/NOWDOC auto-fix, byte-for-byte value
preservation, and the non-fixable (detection-only) guarantee for concatenation
live at `tests/Ruleset/MultilineStringsTest.php`.

## What remains code review

Nothing about *detecting* a multi-line quoted string or a line-wrapped
concatenation — that is fully machine-enforced, and the single-string case is
auto-fixed. Collapsing a flagged multi-line concatenation into one HEREDOC (and
deciding HEREDOC vs NOWDOC when interpolation is involved) is the manual work
the sniff surfaces but does not perform.
