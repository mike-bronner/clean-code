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

`tests/Standards/BooleanOperatorSpacingTest.php` pins this directly, by
intersecting this sniff's `register()` against each of the other three — no
fixture involved, so it holds for every token either side claims rather than
for whichever ones an example happens to use.
`tests/Integration/OperatorRulesIntegrationTest.php` pins the same property
end-to-end over the whole master ruleset, though for the *other three* sniffs'
operators rather than these five: both of its boolean operators are
newline-wrapped, which `ignoreNewlines` deliberately suppresses here.

### The exclusions the split also rests on

Narrowing `register()` prevents this sniff from doubling its siblings. It does
nothing about the reverse — two sniffs cover these same five tokens outright,
and only their absence from `rules.xml` keeps each violation to one diagnostic:

| Sniff | Overlap | Kept out by |
|---|---|---|
| `PSR12.Operators.OperatorSpacing` | adds `Tokens::$booleanOperators` to the Squiz set it inherits | the blanket `<exclude>` on the `PSR12` ref — which must stay unscoped |
| `Squiz.WhiteSpace.LogicalOperatorSpacing` | the same "exactly one space" rule on the same five tokens | never referenced, individually or via the whole `Squiz` standard |

`BooleanOperatorSpacingTest.php` asserts both — that each still overlaps, and
that each is still absent from the master ruleset — plus that no *other* active
sniff registers these tokens beyond the two that deliberately do for unrelated
concerns (`CleanCode.Operators.OperatorLineBreak` for where a wrapped
expression breaks, `Generic.PHP.LowerCaseKeyword` for the word forms' casing).

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
