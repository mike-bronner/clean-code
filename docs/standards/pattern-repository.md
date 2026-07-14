# Pattern: Repository

## Standard

Laravel models are the de-facto persistence repository. The repository
behaviour — persistence methods, attribute/query traits — is realised through
the Models standards rather than dedicated repository classes. For the
concrete model-side conventions, see the
[Models: Persistence Methods (Repository Pattern)](https://github.com/mike-bronner/phpcs-rules/issues/37)
standard; this entry cross-references it rather than duplicating its content.

**Takeaway:** don't create dedicated `Repository` classes; the model *is* the
repository, shaped by the Models standards.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. It is **not** enforced by a
PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Whether repository *behaviour* lives in models — spread across
persistence methods and traits throughout a codebase — is an architectural
judgement no single file's tokens can decide.

## Partial enforcement assessment

One narrow slice **is** catchable by a token-based sniff: dedicated repository
classes announce themselves by name. A declaration like
`class UserRepository`, `interface OrderRepositoryInterface`, or anything
inside a `Repositories` namespace is a strong token-level signal that the
standard is being violated, regardless of where the behaviour actually lives.

A focused sniff issue has been opened for exactly that slice —
[#126](https://github.com/mike-bronner/phpcs-rules/issues/126) — rather than
folding a sniff into this documentation-only standard. The semantic core of
the standard (repository behaviour realised through models) remains enforced
by code review.
