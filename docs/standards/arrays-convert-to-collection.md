# Arrays: Convert To Collection

## Standard

- Whenever possible use collections for manipulation.

**Why:**

- Collections provide a large list of optimized manipulation methods.
- By relying on the framework, we decouple from the direct PHP implementation,
  which may change over major versions, while the framework maintains an
  optimized implementation.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The trigger pattern of this standard **is statically lintable**: bare calls to
native array-manipulation functions (`array_map()`, `array_filter()`,
`array_reduce()`) are token-visible — a function-name token followed by an
open parenthesis, not preceded by `->`, `::`, `new`, or `function` — and each
has a direct Collection equivalent (`collect()->map()/filter()/reduce()`).
Focused sniff issue:
[#165](https://github.com/mike-bronner/phpcs-rules/issues/165). Its mirror
image —the same functions applied to a value that is *already* a Collection —
is [Collections: Only Use Collection
Methods](collections-only-use-collection-methods.md), enforced today by
`CleanCode.Collections.OnlyUseCollectionMethods`.

- **Detection** — a global-scope call to a native array function from the
  configured list is flagged as a collection-pipeline candidate, with the
  Collection equivalent named in the message (`array_map()` →
  `collect()->map()`).
- **Configurable function list** — the flagged functions are a public sniff
  property so projects can tune coverage; the shipped default is `array_map`,
  `array_filter`, `array_reduce` (the highest-signal trio with one-to-one
  Collection equivalents).
- **Warning severity, not error** — the standard says "whenever possible", so
  justified native usage (hot paths, plain-PHP contexts) is tolerated; the
  sniff points at conversion candidates rather than mandating a fix.
- **Boundaries** — method calls (`$obj->array_map()`), static calls, and
  definitions of same-named functions stay out; the sniff cannot verify that
  Laravel / `illuminate/collections` is available in the scanned project, so
  projects without collections exclude the sniff from their ruleset.

## What remains code review

Whether a particular manipulation is *better* as a collection pipeline is a
judgement about context, not tokens. `foreach` loops that accumulate into
arrays are also collection candidates but are not reliably token-detectable.
Those calls stay with code review.
