# Pattern: Repository

## Standard

Laravel models are the de-facto persistence repository. The repository
behaviour — persistence methods, attribute/query traits — is realised through
the Models standards rather than dedicated repository classes.

The model-side conventions are defined by
[Models: Persistence Methods (Repository Pattern)](models-persistence-methods-repository-pattern.md)
([#37](https://github.com/mike-bronner/phpcs-rules/issues/37)), which asks for
descriptive persistence methods on the model itself, organised into single-use
traits; this entry cross-references that standard rather than restating it.

**Takeaway:** don't create dedicated `Repository` classes; the model *is* the
repository, shaped by the Models standards.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 for the semantic half only, one slice is lintable

The standard splits in two, and only one half is Tier 3: whether persistence
logic actually lives in the model or its traits is **not** statically
enforceable — that is a judgement about intent, and no token-based sniff can
verify it — while the complementary half, "no dedicated class outside the model
implements the repository pattern", is token-visible and is getting a sniff of
its own.

The Tier-3 rationale, in one sentence: repository *behaviour* is spread across
persistence methods and traits throughout a codebase, so a single file's token
stream cannot decide whether that behaviour has been placed where the standard
requires. That half is enforced by **code review and developer discipline**.

## The lintable slice

One shape announces itself in the tokens: a dedicated, non-model class,
interface, trait, or enum that implements the repository pattern and names
itself for it — a declaration named `*Repository` (`UserRepository`) or
`*RepositoryInterface` (`OrderRepositoryInterface`), or any declaration inside
a `Repositories\` namespace. That declaration is the thing this standard rules
out, whatever its body does.

That slice is tracked and being implemented in
[#126](https://github.com/mike-bronner/phpcs-rules/issues/126), as a focused
sniff shipping in its own pull request — no sniff ships under this
documentation entry.

- **Boundaries** — the heuristic reads names, not behaviour, so it is evadable:
  a dedicated persistence class called `UserStore`, or one sitting outside a
  `Repositories\` namespace, does exactly what the standard forbids and the
  sniff will stay silent. And a future hit from the
  [#126](https://github.com/mike-bronner/phpcs-rules/issues/126) sniff is a
  hint, not a verdict — code review still decides whether a flagged declaration
  actually violates this standard, since a name alone cannot show that the
  class drives model persistence.

## What remains code review

The semantic core — that persistence behaviour lives on the model and in its
attribute/query traits — stays with code review. The
[#126](https://github.com/mike-bronner/phpcs-rules/issues/126) sniff will cover
one naming shape; it says nothing about where behaviour ended up, so the
reviewer owns that call regardless.
