# Conditionals: Ternary Conditionals

## Standard

- Use ternary operators instead of if-statements where possible.
- Do not nest ternary conditions; instead assign to variables or refactor to
  methods.

**Why:** makes code easier to parse (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1

Two rules in the master `rules.xml` enforce the two halves of the standard
([#20](https://github.com/mike-bronner/phpcs-rules/issues/20)):

- **Prefer ternaries —
  `SlevomatCodingStandard.ControlStructures.RequireTernaryOperator`** flags an
  `if`/`else` whose branches each hold a single `return` or a single plain
  assignment to the same variable, i.e. exactly the statements a one-level
  ternary expresses. Auto-fixable, except when a branch contains a comment or
  the condition uses the word operators `and`/`or`/`xor` (rewriting those
  inline changes precedence), in which case it reports without fixing.
- **No nested ternaries — `CleanCode.Conditionals.DisallowNestedTernary`**
  (custom sniff). The candidate Slevomat rule named in the issue,
  `ControlStructures.DisallowNestedTernaryOperator`, does not exist in
  slevomat/coding-standard 8.15, and PHP_CodeSniffer 3.13 ships no equivalent
  (`Squiz.PHP.DisallowInlineIf` bans *all* ternaries — the opposite of this
  standard), so the nesting half is a custom sniff. It flags a ternary nested
  in another ternary's condition, then-branch, or else-branch — parenthesized
  (`$a ? ($b ? 1 : 2) : 3`) or chained short ternaries (`$a ?: $b ?: $c`) —
  at the inner (nested) operator, once per nesting. Reporting only: unfolding
  a nested ternary means naming an intermediate variable or method, a semantic
  decision no fixer should make.

## Documented sniff limitations (tests assert, never weaken)

`RequireTernaryOperator` detects only the shape it can safely rewrite; these
stay unflagged and are asserted as such in the ruleset test fixtures:

- branches must use curly braces — braceless `if`/`else` is not analyzed;
- only a single plain `=` assignment or `return` per branch — compound
  assignments (`.=`, `+=`, …), multiple statements, and branches assigning
  different variables are out of scope;
- `if`/`elseif`/`else` chains and `if` without `else` are not convertible to
  a single ternary and are correctly ignored (see
  [Conditionals: Mapping Arrays](conditionals-mapping-arrays.md) for chains).

`CleanCode.Conditionals.DisallowNestedTernary` flags *direct* nesting only: a
ternary inside a call argument, an array element, a `match` arm, or an
arrow-function body is bounded by that construct and reads on its own, even
when the construct sits in another ternary's branch — so
`$isRaw ? trim($input ?: 'n/a') : 'none'` passes.
Sibling ternaries (separate arguments, separate array elements, separate
sides of a `match` arm's arrow, or grouped operands of a non-ternary
operator) are not nesting.

## What remains code review

"Where possible" in full generality is judgment: an `if`/`else` whose
branches do more than one assignment or return, or whose intent reads better
as a statement, stays with the reviewer — as does whether a flagged nested
ternary should become a variable, a method, or a `match`.
