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
  rewrite is a judgement call left to the developer. A literal carrying a
  binary-string prefix (`B'Total: ' . $sum`) is detection-only too, for a
  different reason: the interpolated result would be source PHPCS cannot read
  (see the tokenizer note under *Known limitations*).

### `CleanCode.Strings.HtmlAttributeQuotes` — auto-fixable

Within a string literal that contains HTML, flags attribute values written
with apostrophes (`class='card'`) and rewrites them to double quotes. Both PHP
string contexts carry such an attribute and both are handled; what differs is
the escaping. In a double-quoted PHP string the attribute apostrophes are
literal and the replacement quotes need escaping — `"<a class='card'>"` →
`"<a class=\"card\">"`. In a single-quoted one the apostrophes are themselves
escaped and the replacement needs no escaping — `'<a class=\'card\'>'` →
`'<a class="card">'`. The rewrite is scoped to tag spans and gated on the
string actually containing an HTML element, so SQL and prose — `"WHERE name =
'x'"`, or `"<p>Query: name = 'admin'</p>"` between tags — are never touched.

A value carrying a double quote or a backslash is reported without a fix. The
double quote is ambiguous to re-delimit. The backslash is a corruption risk,
because the value is captured out of raw PHP source rather than the evaluated
string: `\'` is not an escape sequence in a double-quoted PHP string, so the
capture can end on a backslash that would then pair with the injected `\"` and
leave a bare quote closing the string early. The two sibling fixers below
decline a backslash for the same reason.

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
  auto-fixed), as is a member, index, or call chain hanging off the closer
  (`($user)->name . 'x'`, `($items)['key'] . 'x'`). A *compound* parenthesized
  operand is deliberately left alone whether or not something is chained onto
  it: `'total: ' . ($count + 1)` opens with a variable just as `($b)` does, but
  `"total: {$count + 1}"` is not valid PHP, so there is no interpolated form to
  steer towards.
- **An interpolated binary-prefixed string is invisible to every sniff here.**
  PHP_CodeSniffer cannot tokenize one: it types the `B"` opener `T_NONE` and
  mis-types the rest of the statement, so there is no token stream to read the
  literal from. `MultilineStrings` refuses such a run outright rather than
  rewrite what is really the closing quote plus the source after it. The
  non-interpolated forms (`B"a\nb"`, `B'a\nb'`) are handled normally, prefix and
  all, and a lowercase `b` is a token of its own that no fixer touches.
- **A tag with unbalanced quotes is never rewritten.** `HtmlAttributeQuotes`
  scopes its rewrite to tag spans that parse, stepping over quoted attribute
  values so a `>` inside one (`<a data-x="a>b" class='y'>`) does not end the
  span. When a tag's quoting does not balance (`<a class="x>`), its attribute
  boundaries are not knowable and the sniff stays silent rather than guess.
  Detection is unaffected: such a string still counts as markup.
- **An odd number of apostrophes inside one tag is the same case, and is also
  left alone.** `<a class='card's'>` is not well-formed HTML — the value's own
  apostrophe closes it — and stepping over quoted values needs them to pair, so
  no span matches and the sniff reports nothing. The variable is the apostrophe
  count, not the PHP string context: this is missed inside both a double-quoted
  and a single-quoted PHP string, and the even-count case is reported inside
  both. Pinned by 'stays silent on a tag whose apostrophes do not pair, in
  either php context'.
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
  touches the delimiters, so it still converts an attribute on any line. It
  does need to know *which* delimiter opened the literal, to pick the escaping
  convention, and it reads that from the opening fragment only — a later
  fragment holds body text, and body text can open with the same characters a
  delimiter is read from (`B'day` reads as a binary prefix plus an apostrophe).
- **A binary-string prefix is carried over where the result stays
  non-interpolating, and blocks the fix where it would not.** The constraint is
  the tokenizer, not PHP: `php -l` accepts `B"Total: {$sum}"`, but PHPCS types
  its `B"` opener `T_NONE` and swallows the source after it, so a fixer emitting
  that shape corrupts the file for the next pass. `EscapeNestedQuotes` and
  `MultilineStrings` therefore keep the prefix — their output never interpolates
  (`B'He said "hi"'` → `B"He said \"hi\""`; `B'a\nb'` → `B<<<'TEXT'`) — while
  `RequireStringInterpolation` refuses a prefixed literal outright, because its
  output always does, and reports it as detection-only instead. Dropping the
  prefix would rest on it being a no-op, which is not this standard's call to
  make. Worth knowing when reading the code: PHPCS splits a lowercase `b` off
  into its own token but leaves an uppercase `B` inside the literal's content,
  so the delimiter is read through `Support\StringLiteral` rather than off the
  token's first character. The invariant behind all of it — no fixer emits
  source the tokenizer cannot read — is swept across every auto-fixable sniff in
  the package by `tests/Contract/SniffContractTest.php`.

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
