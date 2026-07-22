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

## Tests

Following the repo-wide fixture convention, these custom sniffs are **not**
driven through `AbstractSniffUnitTest` (which hardcodes a single
`.inc`/`.inc.fixed` layout). Each sniff drives the real PHPCS engine against
separate fixtures under
`tests/Standards/Fixtures/<SniffClassName>/` — `compliant.inc`,
`violations.inc`, and (where auto-fixable) `autofix-before.inc` /
`autofix-after.inc` — from `tests/Standards/*Test.php`.
