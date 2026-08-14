# Debt: Mental Debt

## Standard

Mental debt is the mental cost required to read code.

- Write as few lines as possible; less code means parsing less.
- Carefully name classes, properties, and methods.
- Do not use abbreviations.
- Keep lines of code under 100 characters; exceed that by breaking to a new
  line.
- Remove code that doesn't accomplish anything.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

Mental debt is the governing concept behind several measurable rules, but the
concept itself — how much mental effort code demands of its reader — is an
architectural/semantic/process judgement. A token-based PHPCS sniff cannot
measure it. This standard is enforced via code review and developer
discipline, not a PHPCS rule.

## Partial enforcement — measurable sub-rules tracked separately

Two takeaways are token-visible slices already tracked by their own focused
sniff issues, so no new sniff issue is opened here:

- **Keep lines under 100 characters** —
  [#3 Line Length](https://github.com/mike-bronner/phpcs-rules/issues/3)
  (Tier 1).
- **Remove code that doesn't accomplish anything** —
  [#29 No Dead Code](https://github.com/mike-bronner/phpcs-rules/issues/29)
  (Tier 2).

Careful naming and "no abbreviations" are **not** sniff-tracked: they are
assessed under
[#18 Naming: Semantic naming principles](https://github.com/mike-bronner/phpcs-rules/issues/18),
which is itself Tier 3 / code-review-only. Narrow token-visible heuristics
spun off from that standard get their own focused sniff issues (see
[#136](https://github.com/mike-bronner/phpcs-rules/issues/136) for magic
numbers); no abbreviation sniff exists today — deciding what counts as an
abbreviation is a semantic judgement, not a token check — so that slice stays
in code review.

## What remains code review

The residual rule — "write as few lines as possible" and the overall mental
cost of a change — is a judgement about clarity and intent that only a human
reader can make. Whether code is as small and as clearly named as it could be
stays with code review.
