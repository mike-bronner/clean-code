# Arrays: Convert To Collection

## Standard

- Whenever possible use collections for manipulation.

**Why:**

- Collections provide a large list of optimized manipulation methods.
- By relying on the framework, we decouple from the direct PHP implementation,
  which may change over major versions, while the framework maintains an
  optimized implementation.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

"Whenever possible" is a semantic judgement: whether a given manipulation
could — and should — be a collection pipeline depends on what the code means,
not on which tokens it uses. A token-based PHPCS sniff cannot verify it, so
**no sniff class is added for this standard**. Enforcement is via code review
and developer discipline.

### Partial-enforcement slice — native array-function calls

A narrow token heuristic can catch a subset: bare calls to native
array-manipulation functions (`array_map()`, `array_filter()`,
`array_reduce()`) are token-visible — a function-name token followed by an
open parenthesis, not preceded by `->`, `::`, `new`, or `function` — and each
has a direct Collection equivalent (`collect()->map()/filter()/reduce()`).
Focused sniff issue:
[#165](https://github.com/mike-bronner/phpcs-rules/issues/165).

- **Detection** — global-scope calls to a configurable list of native array
  functions are flagged as collection-pipeline candidates, with the Collection
  equivalent named in the message.
- **Warning severity, not error** — the standard says "whenever possible", so
  justified native usage (hot paths, plain-PHP contexts) is tolerated; the
  sniff points at conversion candidates rather than mandating a fix.

## What remains code review

Whether a particular manipulation is *better* as a collection pipeline — and
whether the surrounding project even has Laravel collections available — is a
judgement about context, not tokens. `foreach` loops that accumulate into
arrays are also collection candidates but are not reliably token-detectable.
Those calls stay with code review.
