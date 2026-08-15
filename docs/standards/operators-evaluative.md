# Operators: Evaluative

## Standard

- Evaluative operators should never have a new line to the right or left —
  the operator sits on the same line as both of its operands.

```php
if ("hello" === "world") {
    //
}
```

- Comparison: `==`, `===`, `!=`, `<>`, `!==`, `<`, `>`, `<=`, `>=`, `<=>`
- Type: `instanceof`

**Why:** a comparison reads as a single unit; splitting the operator from its
operands hides which values are being compared.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (custom sniff, auto-fixable)

Cleanly enforceable by a static token analyzer: the evaluative operators are
distinct token types, and a violation is simply a line-number difference
between the operator and its nearest non-whitespace neighbor. Implemented by
[#56](https://github.com/mike-bronner/phpcs-rules/issues/56).

### Existing rules evaluated first

- **`Squiz.WhiteSpace.OperatorSpacing`** — close but not exact. It registers
  *all* comparison, arithmetic, assignment, and ternary operators with no
  configuration to narrow the set to evaluative operators, and its
  `SpacingBefore`/`SpacingAfter` error codes conflate "newline found" with
  "expected exactly 1 space", so newline-only enforcement cannot be isolated
  — enabling it would impose single-space rules this standard does not
  define and flag compliant same-line code. It also does not run through
  this package's sniff unit-test harness, which only exercises sniffs inside
  the CleanCode standard.
- **Slevomat** — no sniff addresses newlines around comparison or
  `instanceof` operators.

Per the execution process, the tests were not weakened to fit an existing
rule; a focused custom sniff implements the standard instead.

### Rule

`CleanCode.Operators.DisallowNewlineAroundEvaluativeOperators`

- **Detection** — for each evaluative operator token, the nearest
  non-whitespace, non-comment token on either side must be on the same line.
  A newline before yields `FoundBefore`; a newline after yields `FoundAfter`;
  an operator alone on its line yields both.
- **Auto-fix** — `phpcbf` collapses the newline and surrounding indentation
  to a single space, joining the operator with its operands on one line.
- **Comment boundary** — when a comment sits between the operator and its
  operand, the violation is reported but not auto-fixed: joining the lines
  would move the comment mid-expression, or let a line comment swallow the
  rest of the statement.
- **Out of scope** — the number of spaces around a same-line operator. This
  standard only forbids newlines; horizontal spacing is a separate concern.
