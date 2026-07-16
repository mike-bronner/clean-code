# Methods: Type Hints

## Standard

Methods should have type-hinted parameters as well as a return type.

**Why:**

- Helps maintainability and readability, and is self-documenting (mental debt).
- Traps logic errors close to the source (technical debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (Slevomat, configured)

Enforced by two existing Slevomat sniffs wired into the master `rules.xml`:

- `SlevomatCodingStandard.TypeHints.ParameterTypeHint` — every method
  parameter must carry a native type hint. Reported as `MissingAnyTypeHint`
  when neither a hint nor a `@param` annotation exists, or as the
  auto-fixable `MissingNativeTypeHint` when a `@param` annotation can be
  promoted to a native hint.
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint` — every method must
  declare a native return type. Methods that return nothing are auto-fixed to
  `: void`; a promotable `@return` annotation is auto-fixed to the native
  hint; a method that returns a value with no hint and no annotation is
  reported (not fixable — the type cannot be inferred).

### Configuration decisions

- **`enable*` properties are pinned** to the package's PHP 8.1 floor
  (`object`, `mixed`, `union`, `intersection`, `static`, `never` enabled;
  standalone `null`/`true`/`false` hints disabled — those are PHP 8.2+).
  Left unset, Slevomat resolves them from the PHP version running the check,
  so pinning keeps behaviour identical across environments.
- **`MissingTraversableTypeHintSpecification` and `UselessAnnotation` are
  excluded** — they police docblock hygiene (`@param array<int, string>`
  specifications, annotations duplicating native hints), not missing native
  hints. Docblock and property coverage belongs to the general Type Hints
  standard ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)).

### Edge-case behaviour

- `__construct`, `__destruct`, and `__clone` are exempt from return type
  hints (PHP forbids them); their parameters — including promoted
  constructor properties — are still checked.
- Other magic methods (`__toString`, etc.) are fully checked.
- Closures are not checked for parameter hints; a closure that returns no
  value must declare `: void` (auto-fixed), while one returning a value is
  not checked. Arrow functions are not checked at all.
- Methods carrying an `@inheritDoc` annotation are skipped — their signature
  is the parent's.

## What remains code review

Whether the chosen types are the *right* types — an overly wide `mixed` or a
sloppy union satisfies the sniff but not the standard's intent. Property type
hints and docblock quality are covered by the general Type Hints standard
(#45), not this rule.
