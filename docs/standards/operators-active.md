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

## Enforceability — Tier 1 (custom sniff, auto-fixable)

Enforced by **`CleanCode.Operators.ActiveOperatorSpacing`**
([#62](https://github.com/mike-bronner/phpcs-rules/issues/62)), which extends
`Squiz.WhiteSpace.OperatorSpacing` rather than reimplementing its spacing
checks and fixers. The Squiz sniff already enforces exactly one space around
every assignment operator in the list; the extension adds the operators it
does not target — `&&`, `||`, `and`, `or`, `xor`, string concatenation `.`,
and the unary `!`.

- **Exactly one space** is required on each side of every binary operator in
  the list — including alignment padding before `=`, which the stock Squiz
  sniff permits and this sniff does not.
- **Unary `!`** acts on the operand that follows it, so it requires exactly
  one space after itself only; spacing before it belongs to the preceding
  token's rules. Chained negation `!!$foo` fixes to `! ! $foo`.
- **Newlines count as valid separation**, so multi-line concatenation and
  multi-line logical expressions (operator at line end or line start) are
  untouched.
- **Auto-fixable:** every violation is corrected by `phpcbf`, inserting or
  collapsing to exactly one space without touching surrounding code.

## Boundaries

- `=>` in arrays and `match` arms is syntax, not an active operator, and is
  not targeted.
- Default values in function signatures (`function foo(int $a = 1)`) are
  skipped, inherited from the Squiz sniff — declaration-spacing rules own
  that context.
- Unary minus/plus (`-$x`), references (`=&`), and comparison/arithmetic
  operators are outside this standard's operator list and untouched.
