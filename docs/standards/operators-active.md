# Operators: Active

## Standard

Active operators have a space between themselves and the object they are
acting on:

- Arithmetic assignment: `=`, `+=`, `-=`, `/=`, `*=`, `%=`, `**=`
- Bitwise assignment: `&=`, `|=`, `^=`, `<<=`, `>>=`
- Other assignment: `.=`, `??=`
- Logical: `and`, `or`, `xor`, `!`, `&&`, `||`
- String: `.`

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (auto-fixable)

The standard is enforced by **four sniffs, each owning a disjoint slice of the
list** ([#62](https://github.com/mike-bronner/phpcs-rules/issues/62)). Three of
them were already wired into `rules.xml` for
[#35](https://github.com/mike-bronner/phpcs-rules/issues/35); only the boolean
connectives needed a new sniff.

| Operators | Sniff | Wired in for |
|---|---|---|
| every assignment operator — `=`, `+=`, `-=`, `/=`, `*=`, `%=`, `**=`, `&=`, `|=`, `^=`, `<<=`, `>>=`, `.=`, `??=` | `Squiz.WhiteSpace.OperatorSpacing` | #35 |
| string concatenation `.` | `Squiz.Strings.ConcatenationSpacing` | #35 |
| logical not `!` | `CleanCode.Operators.NotOperatorSpacing` | #35 |
| `&&`, `||`, `and`, `or`, `xor` | `CleanCode.Operators.BooleanOperatorSpacing` | **#62** |

`CleanCode.Operators.BooleanOperatorSpacing` extends
`Squiz.WhiteSpace.OperatorSpacing` rather than reimplementing its spacing
checks and fixers, and re-points `register()` at `Tokens::$booleanOperators` —
the five tokens the Squiz sniff's own `register()` leaves out. It reports the
parent's four codes: `NoSpaceBefore`, `NoSpaceAfter`, `SpacingBefore`,
`SpacingAfter`.

### Why the split is load-bearing

Three quarters of this operator list was already policed before #62. A single
sniff covering the whole list therefore reports every assignment,
concatenation, and `!` violation **twice** — PHP_CodeSniffer's "a later rule's
configuration of the *same* sniff wins" merge collapses duplicate
configuration, but it cannot collapse diagnostics from two *different* sniffs.
Narrowing the new sniff to the one group nothing else covers is what keeps
exactly one diagnostic per violation.

`tests/Integration/OperatorRulesIntegrationTest.php` pins this end-to-end: it
runs the whole master ruleset over one fixture and asserts an exhaustive
line → source map, so a re-broadened `register()` here fails the suite.
`tests/Standards/BooleanOperatorSpacingTest.php` pins it directly as well, by
asserting this sniff shares no registered token with any of the other three.

### Behaviour

- **Exactly one space** on each side of every operator in the list. For
  assignments this includes alignment padding before `=`, which the stock Squiz
  sniff permits and `rules.xml` switches off via
  `ignoreSpacingBeforeAssignments`.
- **Unary `!`** acts on the operand that follows it, so it requires exactly one
  space after itself only; spacing before it belongs to the preceding token's
  rules.
- **Newlines count as valid separation.** All four sniffs run with
  `ignoreNewlines`, so a wrapped expression — operator at line end or at line
  start — is untouched here. How it wraps belongs to
  `CleanCode.Operators.OperatorLineBreak` (#35) and
  `CleanCode.Conditionals.OneConditionPerLine`.
- **Auto-fixable:** every violation is corrected by `phpcbf`, inserting or
  collapsing to exactly one space without touching surrounding code.

## Boundaries

- `=>` in arrays and `match` arms is syntax, not an active operator, and is not
  targeted.
- Default values in function signatures (`function foo(int $a = 1)`) are
  skipped — declaration-spacing rules own that context.
- Unary minus/plus (`-$x`), references (`=&`), and comparison/arithmetic
  operators are outside this standard's operator list. Comparison and
  arithmetic spacing is still enforced by `Squiz.WhiteSpace.OperatorSpacing`,
  which registers those tokens for its own reasons — that is not this
  standard's doing.
