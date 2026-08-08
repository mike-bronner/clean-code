# Type Hints and Return Types

## Standard

- Type hint all method parameters and return values.
- Type hints serve as documentation, making code more fluent and readable.
- Type hints and return types prevent some logic errors from propagating,
  catching them as close as possible to their source.

**Why:**

- Catches logic issues related to unexpected data changes close to the source.
- Makes code easier to understand (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (existing sniffs, configured)

Enforced by three Slevomat sniffs wired into the master `rules.xml`
([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)):

- `SlevomatCodingStandard.TypeHints.ParameterTypeHint` — every method
  parameter (including promoted constructor properties and variadics)
  carries a native type hint.
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint` — every method declares
  a native return type (constructors and destructors excepted, per PHP).
- `SlevomatCodingStandard.TypeHints.PropertyTypeHint` — every property
  carries a native type hint.

Only the missing-hint codes (`MissingAnyTypeHint`, `MissingNativeTypeHint`)
are enforced. The annotation-centric codes — traversable `@param`/`@var`
specifications (`MissingTraversableTypeHintSpecification`), redundant-doc
cleanup (`UselessAnnotation`), and `LessSpecificNativeTypeHint` — police doc
blocks, which this standard does not mandate, and stay excluded.

Sniff behaviour is pinned to the package's PHP floor via
`<config name="php_version" value="80100"/>` so version-gated options
(union/intersection hints, standalone `null`/`true`/`false`) resolve the
same on every runtime. Nullable, union, and intersection native hints all
satisfy the rule.

### Auto-fix

`phpcbf` resolves every violation where the missing native hint can be
inferred from an existing `@param`/`@return`/`@var` annotation
(`MissingNativeTypeHint`). Violations with no annotation to infer from
(`MissingAnyTypeHint`) are flagged but must be fixed by hand — the sniff
cannot guess a type.

## What remains code review

Whether the *chosen* type is right — an over-wide `mixed`, a nullable that
should be non-nullable, or a missing generics-style annotation on a
traversable — stays a code-review judgement.
