# Indentation: Multi-Line Statements

## Standard

- If a single statement extends over multiple lines, any lines subsequent to
  the first should be indented by one level.

**Why:** indicates coherence between lines of code (mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (custom sniff)

Enforced by `CleanCode.WhiteSpace.MultiLineStatementIndent`, auto-fixable via
`phpcbf`. The sniff applies the standard as an exact rule — one level is
4 spaces:

- **Continuation lines inside parentheses or brackets** (call arguments,
  array items, condition expressions) sit exactly one level in from the line
  containing the opener.
- **Operator-led continuation lines** (`->`, `?->`, `.`, `?`, `:`, `&&`,
  `||`, …) sit exactly one level in from the line where their expression
  started, so nested chains indent relative to their receiver, not the
  statement.
- **A closing bracket on its own line** matches the indent of the line that
  opened the bracket.
- **Scope bodies are out of scope**: lines inside closures, anonymous
  classes, and match expressions are governed by scope-indent rules, not by
  this sniff.

### Why a custom sniff

Existing rules were evaluated against the full fixture suite
(`CleanCode/Tests/WhiteSpace/MultiLineStatementIndentUnitTest.inc`) before
writing one:

- `Generic.WhiteSpace.ScopeIndent` treats continuation-line indent as
  non-exact and flags none of the violations.
- `PEAR.WhiteSpace.ObjectOperatorIndent`, `Generic.Arrays.ArrayIndent`, and
  `PEAR.Functions.FunctionCallSignature` each match the expected semantics
  but only for one construct (chains, arrays, calls); nothing covers
  string concatenation, ternaries, or boolean-condition indent.
- `PSR12.ControlStructures.ControlStructureSpacing` false-positives on a
  compliant fixture (it additionally requires the first condition expression
  on its own line — a constraint this standard does not impose).
- `SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition`
  governs *when* a condition must become multi-line, not how continuation
  lines are indented — a different concern.

Configuring the partial matches would leave gaps and weaken the tests, so
the standard gets one custom sniff covering every construct uniformly.
