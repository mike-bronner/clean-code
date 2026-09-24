# Pattern: Repository

## Standard

Laravel models are the de-facto persistence repository. The repository
behaviour — persistence methods, attribute/query traits — is realised through
the Models standards rather than dedicated repository classes.

The model-side conventions are defined by
[Models: Persistence Methods (Repository Pattern)](models-persistence-methods-repository-pattern.md)
([#37](https://github.com/mike-bronner/clean-code/issues/37)), which asks for
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
implements the repository pattern", is token-visible and has a sniff of its
own.

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

That slice is enforced by the custom
`CleanCode.Pattern.DisallowRepositoryClasses` sniff
([#126](https://github.com/mike-bronner/clean-code/issues/126)), at **warning**
severity and detection-only. It reads the declaration and nothing else:
`extends`, `implements`, a trait `use`, an import, and `new` are consumption
sites, so a class forced to extend a third-party `*Repository` base class is
not reported for it. Both halves — the `Repository`/`RepositoryInterface` name
suffix and the `Repositories` namespace segment — are compared
case-insensitively, as PHP resolves type and namespace names. An anonymous
class is never reported: `new class {}` is a `new` expression, and not a
dedicated type that can be autoloaded, type-hinted, or bound by name.

- **Boundaries** — the heuristic reads names, not behaviour, so it is evadable:
  a dedicated persistence class called `UserStore`, or one sitting outside a
  `Repositories\` namespace, does exactly what the standard forbids and the
  sniff stays silent. And a hit is a hint, not a verdict — code review still
  decides whether a flagged declaration actually violates this standard, since
  a name alone cannot show that the class drives model persistence. That is why
  the sniff warns rather than errors.

## What remains code review

The semantic core — that persistence behaviour lives on the model and in its
attribute/query traits — stays with code review. The
`CleanCode.Pattern.DisallowRepositoryClasses` sniff
([#126](https://github.com/mike-bronner/clean-code/issues/126)) covers one
naming shape; it says nothing about where behaviour ended up, so the reviewer
owns that call regardless.
