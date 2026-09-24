# Type Hints and Return Types

## Standard

- Type hint all parameters, return values, and properties.
- Type hints serve as documentation, making code more fluent and readable.
- Type hints and return types prevent some logic errors from propagating,
  catching them as close as possible to their source.

**Why:**

- Catches logic issues related to unexpected data changes close to the source.
- Makes code easier to understand (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniffs, configured)

This standard is split across two rules that together enforce it end to end:

- **Parameter and return hints** are owned by the
  [Methods: Type Hints](methods-type-hints.md) standard
  ([#70](https://github.com/mike-bronner/clean-code/issues/70)), which wires
  `SlevomatCodingStandard.TypeHints.ParameterTypeHint` and
  `SlevomatCodingStandard.TypeHints.ReturnTypeHint` into `CleanCode/ruleset.xml` for every
  callable (class methods *and* free functions). See that doc for the
  parameter/return details, the pinned `enable*` options, and the edge cases.
- **Property hints** are owned by this standard, enforced by
  `SlevomatCodingStandard.TypeHints.PropertyTypeHint` in the master `CleanCode/ruleset.xml`
  ([#45](https://github.com/mike-bronner/clean-code/issues/45)) — every
  property carries a native type hint.

This split resolves the original overlap between #45 and #70: #70 is the single
home for parameter/return hints across all callables, and #45 keeps the
non-overlapping remainder (`PropertyTypeHint` plus docblock hygiene).

Only the missing-hint codes (`MissingAnyTypeHint`, `MissingNativeTypeHint`) are
enforced for properties. The annotation-centric codes — traversable `@var`
specifications (`MissingTraversableTypeHintSpecification`) and redundant-doc
cleanup (`UselessAnnotation`) — police doc blocks, which this standard does not
mandate, and stay excluded.

Sniff behaviour is pinned to the package's PHP floor via
`<config name="php_version" value="80100"/>` so version-gated options resolve
the same on every runtime. Nullable, union, and intersection native property
hints all satisfy the rule.

### Auto-fix

`phpcbf` resolves every property violation where the missing native hint can be
inferred from an existing `@var` annotation (`MissingNativeTypeHint`).
Properties with no annotation to infer from (`MissingAnyTypeHint`) are flagged
but must be hinted by hand — the sniff cannot guess a type. (Parameter and
return auto-fix from `@param`/`@return` is documented under
[Methods: Type Hints](methods-type-hints.md).)

## What remains code review

Whether the *chosen* type is right — an over-wide `mixed`, a nullable that
should be non-nullable, or a missing generics-style annotation on a
traversable — stays a code-review judgement.
