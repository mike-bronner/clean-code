# Methods: Type Hints

## Standard

Methods should have type-hinted parameters as well as a return type.

**Why:**

- Helps maintainability and readability, and is self-documenting (mental debt).
- Traps logic errors close to the source (technical debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (Slevomat, configured)

Enforced by two existing Slevomat sniffs wired into the master `rules.xml`:

- `SlevomatCodingStandard.TypeHints.ParameterTypeHint` — every parameter must
  carry a native type hint. Reported as `MissingAnyTypeHint` when neither a
  hint nor a `@param` annotation exists, or as the auto-fixable
  `MissingNativeTypeHint` when a `@param` annotation can be promoted to a
  native hint.
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint` — every declaration must
  declare a native return type. Declarations that return nothing are
  auto-fixed to `: void`; a promotable `@return` annotation is auto-fixed to
  the native hint; one that returns a value with no hint and no annotation is
  reported (not fixable — the type cannot be inferred).

### Scope — all callables, not just methods

The Slevomat sniffs register on every `T_FUNCTION`, so this standard covers
**class methods and free (top-level) functions alike** — a free function with
an unhinted parameter or a missing return type is flagged the same as a
method. This is the deliberate scope for #70: it owns parameter and return
type hints for every callable. The general Type Hints standard
([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)) keeps the
non-overlapping remainder — `PropertyTypeHint` and docblock hygiene.

### Configuration decisions

- **`enable*` properties are pinned** to the package's PHP 8.1 floor
  (`object`, `mixed`, `union`, `intersection`, `static`, `never` enabled;
  standalone `null`/`true`/`false` hints disabled — those are PHP 8.2+).
  Left unset, Slevomat resolves them from the PHP version running the check,
  so pinning keeps behaviour identical across environments. With the
  standalone hints disabled, a docblock `@param false`/`@return false` is
  widened to the native `bool` (not the 8.2 standalone `false`), and a
  docblock `@param null` — which has no native 8.1 spelling — is left
  unhinted rather than flagged.
- **`MissingTraversableTypeHintSpecification`, `UselessAnnotation`, and
  `LessSpecificNativeTypeHint` are excluded** — each acts on what a docblock
  says rather than on a missing native hint: item-type specifications
  (`@param array<int, string>`), annotations duplicating the native hint, and
  narrowing a declared `: void` to `: never` on the strength of a
  `@return never` annotation. Docblock and property coverage belongs to the
  general Type Hints standard
  ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45)).

### Edge-case behaviour

- `__construct`, `__destruct`, and `__clone` are exempt from return type
  hints (PHP forbids them); their parameters — including promoted
  constructor properties — are still checked.
- Other magic methods (`__toString`, etc.) are fully checked.
- Closures are not checked for parameter hints; a closure that returns no
  value must declare `: void` (auto-fixed), while one returning a value is
  not checked. Arrow functions are not checked at all.
- Free functions are checked exactly like methods — an unhinted parameter or
  a missing return type is flagged and, where a `@param`/`@return` annotation
  can be promoted, auto-fixed.
- Methods carrying an `@inheritDoc` annotation are skipped — their signature
  is the parent's.

## What remains code review

Whether the chosen types are the *right* types — an overly wide `mixed` or a
sloppy union satisfies the sniff but not the standard's intent. Property type
hints and docblock quality are covered by the general Type Hints standard
(#45), not this rule.
