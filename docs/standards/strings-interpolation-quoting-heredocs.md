# Strings: Interpolation, quoting, HereDocs

## Standard

- Use **interpolation** in favor of concatenation.
- HTML attributes should always use **double quotes**, never apostrophes.
- Defining HTML or other code to be rendered within code should be done using
  **HereDocs**.
- **Escape quotes** when rendering inside other quotes.

**Why:**

- Strings quoted in the same way can be sorted.
- Reduced mental load when reading code with nested quotes.
- Adherence to HTML5 standards.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniffs)

No bundled or Slevomat sniff matches this standard, so four custom sniffs in
the **`CleanCode.Strings`** category enforce it. The candidates named in the
issue were evaluated and rejected rather than bent to fit:

- **`Squiz.Strings.DoubleQuoteUsage`** enforces the *opposite* preference —
  single quotes unless a double-quoted feature is used — so it would flag the
  double-quoted, interpolation-ready strings this standard wants. Rejected.
- **Slevomat `Strings.DisallowVariableParsing`** forbids interpolation
  outright, the direct inverse of "use interpolation in favor of
  concatenation." Rejected.

### `CleanCode.Strings.RequireStringInterpolation` — auto-fixable

Flags a `.` concatenation that joins a string literal with a variable — text
that reads more cleanly as one interpolated string. Only concatenations whose
every operand can appear inside an interpolated string (literals and variable
expressions) are considered; mixing in a function call, constant, or magic
constant (`$x . foo()`, `__DIR__ . '/x'`) means the expression cannot become a
single interpolated string, and it is left alone.

- **Auto-fixed** — the direct, two-operand case: one string literal plus one
  plain `$variable` (`'Hello ' . $name` → `"Hello {$name}"`). The fixer
  brace-wraps the variable so it never runs into adjacent literal text.
- **Detection-only** — multi-expression chains (`'a' . $b . 'c'`) and complex
  variable operands (`'x' . $obj->prop`, `'x' . $arr['k']`,
  `'x' . $svc->run()`). These are interpolatable with `{...}`, but the safe
  rewrite is a judgement call left to the developer.

### `CleanCode.Strings.HtmlAttributeQuotes` — auto-fixable

Within a string literal that contains HTML, flags attribute values written
with apostrophes (`class='card'`) and rewrites them to double quotes. Because
an apostrophe-quoted attribute can only survive inside a *double*-quoted PHP
string, the fixer escapes the replacement for that context:
`"<a class='card'>"` → `"<a class=\"card\">"`. The scan is gated on the string
actually containing an HTML element, so SQL/prose such as
`"WHERE name = 'x'"` is never touched.

### `CleanCode.Strings.RequireHeredocForMarkup` — detection-only

Flags HTML/markup embedded in a regular single- or double-quoted string:
such content belongs in a HereDoc, where it reads without quote escaping and
survives multi-line growth. Compliant HereDoc/NowDoc bodies tokenize
separately and never trip the sniff. No auto-fixer — converting an inline
string to a HereDoc is a structural edit (a dedented closing marker, no
trailing concatenation) the standard leaves to the developer.

### `CleanCode.Strings.EscapeNestedQuotes` — auto-fixable

The standard's rationale makes the double quote the canonical delimiter: when
a string must contain a double-quote character, escape it (`\"`) rather than
switching the whole literal to single quotes just to dodge the escape. Flags a
single-quoted literal that carries a double quote (`'He said "hi"'`) and, when
meaning-preserving, rewrites it to `"He said \"hi\""`. (PHP cannot tokenize an
*un*escaped delimiter nested in a same-quoted string at all, so the
delimiter-switch dodge is the reachable form of the rule.) The fixer runs only
when the literal carries no `$`/`{` interpolation trigger and no backslash
escape; otherwise the violation is reported for manual conversion.

## Known limitations

- **Grouping parentheses are transparent, but only around a single token.**
  `($b) . 'y'` is reported (detection-only — the parenthesized operand is never
  auto-fixed). A *compound* parenthesized operand is deliberately left alone:
  `'total: ' . ($count + 1)` opens with a variable just as `($b)` does, but
  `"total: {$count + 1}"` is not valid PHP, so there is no interpolated form to
  steer towards.
- **A tag with unbalanced quotes is never rewritten.** `HtmlAttributeQuotes`
  scopes its rewrite to tag spans that parse, stepping over quoted attribute
  values so a `>` inside one (`<a data-x="a>b" class='y'>`) does not end the
  span. When a tag's quoting does not balance (`<a class="x>`), its attribute
  boundaries are not knowable and the sniff stays silent rather than guess.
  Detection is unaffected: such a string still counts as markup.
- **A single tag split across source lines is not rewritten.** PHPCS hands the
  sniff one token per physical line, and no single token holds that tag's span.
- **`RequireHeredocForMarkup` reports once per physical line.** Same
  tokenization: a multi-line quoted string arrives as one fragment per line, and
  every fragment carrying a tag is reported. The sibling
  `CleanCode.Strings.MultilineStrings` reports the same string once, and its
  auto-fix to a HereDoc resolves all of them together.
- **A literal spanning several source lines is never re-delimited or merged.**
  `RequireStringInterpolation` and `EscapeNestedQuotes` both rewrite a whole
  literal, and no single token holds one that spans lines — so both stay silent
  on that shape and leave it to `CleanCode.Strings.MultilineStrings`, whose
  HereDoc conversion is the rewrite that shape actually wants.
  `HtmlAttributeQuotes` is unaffected: it edits inside a fragment and never
  touches the delimiters, so it still converts an attribute on any line.
- **A binary-string prefix is carried over, not dropped.** `b'x'` / `B"y"` are
  handled in all three fixers, and the prefix survives the rewrite
  (`B"Total: " . $sum` → `B"Total: {$sum}"`). Worth knowing when reading the
  code: PHPCS splits a lowercase `b` off into its own token but leaves an
  uppercase `B` inside the literal's content, so the delimiter is read through
  `Support\StringLiteral` rather than off the token's first character.

## Tests

These sniffs follow the repo-wide fixture contract (see `CONTRIBUTING.md`):
per-sniff fixtures at `tests/fixtures/<SniffClassName>/` under the fixed names
`passing.php`, `failing.php`, and — for the three auto-fixable sniffs —
`autofixed.php`, which is `phpcbf`'s own output for `failing.php`. All four
sniffs are registered in `SWEPT_SNIFFS` (and the three fixable ones in
`AUTOFIXABLE_SNIFFS`) in `tests/Sniffs.php`, which gives them the generic
passing/failing/autofix/idempotence sweep plus the shipped-package smoke test.
Per-sniff line/column behaviour lives in `tests/Standards/*Test.php`, and the
interaction with `CleanCode.Strings.MultilineStrings` in
`tests/Ruleset/StringsStandardTest.php`.
