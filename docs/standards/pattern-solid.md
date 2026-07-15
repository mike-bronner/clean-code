# Pattern: SOLID

## Standard

- **Single Responsibility**: a class should have one and only one reason to
  change (a Model defines relationships/scopes/attributes; a Controller handles
  request/response; an Action is a single-purpose invokable).
- **Open-Closed**: objects should be open for extension but closed for
  modification.
- **Liskov Substitution**: classes and their sub-classes should be substitutable
  without breaking code.
- **Interface Segregation**: clients shouldn't be forced to depend on methods
  they don't use; split interfaces when signatures don't apply to all
  implementers.
- **Dependency Inversion**: depend on abstractions, not concretions; specify the
  interface instead of the concrete class (Action classes are a good example).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. It is **not** enforced by a PHPCS
sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time.
Whether a class has one reason to change, whether a hierarchy is substitutable,
or whether a dependency is an abstraction are judgements about design intent
and cross-file relationships that no single file's tokens can decide.

## Partial enforcement assessment

Each principle was assessed for a narrow, token-based heuristic that could
catch a subset:

- **Single Responsibility** — *covered by existing tracked sniffs.* The
  token-visible proxies for "too many responsibilities" are size and coupling
  metrics, all already tracked as their own sniff issues: TooManyMethods
  ([#80](https://github.com/mike-bronner/phpcs-rules/issues/80)),
  TooManyPublicMethods
  ([#83](https://github.com/mike-bronner/phpcs-rules/issues/83)),
  ExcessiveClassComplexity
  ([#87](https://github.com/mike-bronner/phpcs-rules/issues/87)),
  ExcessiveClassLength
  ([#93](https://github.com/mike-bronner/phpcs-rules/issues/93)),
  ExcessivePublicCount
  ([#96](https://github.com/mike-bronner/phpcs-rules/issues/96)),
  TooManyFields
  ([#98](https://github.com/mike-bronner/phpcs-rules/issues/98)), and
  CouplingBetweenObjects
  ([#114](https://github.com/mike-bronner/phpcs-rules/issues/114)). No new
  issue — duplicating them under an SRP banner adds nothing.
- **Open-Closed** — *none found.* Whether a change extends or modifies is a
  property of the change and the design around it, not of any one file's
  tokens. Code review only.
- **Liskov Substitution** — *heuristic found.* A method whose entire body is a
  single `throw` statement, inside a class that `extends` or `implements`, is
  a single-file token signal of *refused bequest* — the subtype rejecting
  behaviour its supertype promises. Focused sniff issue:
  [#131](https://github.com/mike-bronner/phpcs-rules/issues/131).
- **Interface Segregation** — *heuristic found.* An `interface` declaring more
  than a threshold of method signatures is a countable fat-interface signal —
  the wider the surface, the likelier that signatures don't apply to all
  implementers. Focused sniff issue:
  [#132](https://github.com/mike-bronner/phpcs-rules/issues/132).
- **Dependency Inversion** — *none found.* Tokens in one file cannot tell
  whether a type hint names an interface or a concrete class — that requires
  project-wide knowledge. Injection conventions are tracked separately under
  Dependency Injection
  ([#72](https://github.com/mike-bronner/phpcs-rules/issues/72)); the
  abstraction-over-concretion judgement stays with code review.

The semantic core of all five principles remains enforced by code review.
