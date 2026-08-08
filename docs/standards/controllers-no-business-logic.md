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

This is an **architectural standard**. No PHPCS rule is wired into
`rules.xml` for it; enforcement is code review and developer discipline.

Two properties put it out of a sniff's reach:

- **"Business logic" is semantic, not a token shape.** PHPCS sees the same
  `T_STRING` / `T_OBJECT_OPERATOR` tokens whether a statement dispatches a
  response or calculates an invoice, so no token analysis can decide which
  statements *belong* in a controller.
- **The prescribed remedy is cross-file.** Extracting logic into Form Request
  and Response classes is an architectural move across several files, and a
  PHPCS sniff analyzes one file at a time, with no knowledge of what the
  extracted classes contain or whether they exist at all.

## Partial enforcement assessment

Each slice of the standard was assessed against what a single-file token
sniff can actually decide. One slice is feasible and is tracked separately;
the other two are not.

- **Non-RESTful public method names — feasible, tracked as
  [#141](https://github.com/mike-bronner/phpcs-rules/issues/141).** The "RESTful
  or invokable; no custom actions" rule maps onto data a sniff really has: a
  class name ending in `Controller`, plus the name and visibility of each
  method, are all plain single-file tokens. A sniff can flag any public method
  outside the seven resource actions (`index`, `create`, `store`, `show`,
  `edit`, `update`, `destroy`) and `__construct` / `__invoke`, with a
  configurable allowlist for framework hooks. It is convention-dependent and
  cannot prove the class is actually routed, so it is a faithful slice rather
  than the whole rule.
- **Method-length cap — rejected.** "Leaving only a few lines per method"
  names no number, so any threshold would be invented rather than derived, and
  line count is a poor proxy for "no business logic": a long controller method
  can be pure flow control, and a three-line one can hide a pricing rule.
  Generic metrics tooling already covers method length.
- **The core rule itself — rejected.** Deciding whether a statement is
  request/response flow or business logic is the semantic judgement described
  above, and is not token-decidable.

## What remains code review

Everything except the naming slice in
[#141](https://github.com/mike-bronner/phpcs-rules/issues/141). Whether a
controller method only moves a request to a response — and whether the logic
it delegates has landed in the right Form Request or Response class — is a
judgement about meaning and structure, not tokens. That call stays with code
review.
