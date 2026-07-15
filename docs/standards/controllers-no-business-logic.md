# Controllers: No Business Logic

## Standard

- Controllers should only control the flow of requests and responses; all
  business logic should be extracted to Form Request classes and Response
  classes, leaving only a few lines per method.
- Controllers should be either RESTful or invokable; no custom actions.
  Reaching for custom actions is a code smell that the controller or model
  hasn't been named or extracted granularly enough.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an **architectural standard**. It is **not** enforced by a PHPCS sniff.
Enforcement is via **code review and developer discipline**.

"Business logic" is a semantic judgment, not a token shape: PHPCS sees the same
`T_STRING`/`T_OBJECT_OPERATOR` tokens whether a statement dispatches a response
or calculates an invoice, so no single-file token analysis can decide which
statements *belong* in a controller. The prescribed remedy — extracting logic
into Form Request and Response classes — is a cross-file architectural move,
and PHPCS sniffs analyze one file at a time with no knowledge of what the
extracted classes contain or whether they exist.

## Partial enforcement assessment

One narrow token-visible slice exists and is tracked as a follow-up sniff
issue: [#141](https://github.com/mike-bronner/phpcs-rules/issues/141).

- **Non-RESTful public method names** (the "RESTful or invokable; no custom
  actions" rule) — a class whose name ends in `Controller`, and the names and
  visibility of its methods, are all plain single-file token data. A sniff can
  flag any public method outside the seven resource actions (`index`,
  `create`, `store`, `show`, `edit`, `update`, `destroy`) and the magic
  methods (`__construct`, `__invoke`), with a configurable allowlist for
  framework hooks. Convention-dependent and unable to prove the class is
  actually routed, but a faithful, low-false-positive slice. **Follow-up sniff
  issue opened: #141.**
- **Method-length cap** ("leaving only a few lines per method") — considered
  and rejected. The standard names no number, so any threshold would be
  invented rather than derived, and generic metrics tooling already covers
  method length. Line count is not a faithful proxy for "no business logic".
- **The core rule itself** — deciding whether a statement is request/response
  flow or business logic is semantic; not token-decidable (see above).

Resolution: **documentation-only** for this standard — the full rule remains
enforced by code review, with the narrow naming slice tracked separately
in [#141](https://github.com/mike-bronner/phpcs-rules/issues/141).
