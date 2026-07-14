# Testing: Development Process (TDD)

## Standard

- Write unit tests before implementing classes (only implement classes, never
  procedural code).
- Always do Red/Green/Refactor TDD; write tests for the code you'd like in an
  optimal world, make the failing test pass with minimum code, expand,
  refactor, repeat until MVP.
- Two perspectives: when writing tests, keep the larger business domain in
  mind; when writing code to satisfy tests, only think about the test (do not
  think about business logic).
- As tests get more specific, code should become more generic; consider the
  Transformation Priority Premise.
- Never add code that won't be used; remove unused code.
- Use cyclomatic complexity as a guide for the number of tests (≈1 test per
  complexity unit).
- Wait to DRY out duplication until a few tests cover it, so the correct
  abstraction reveals itself.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is a development-*process* standard. It is **not** enforced by a PHPCS
sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Whether a test was written *before* its implementation, whether code was
grown through Red/Green/Refactor cycles, or whether the author held the right
perspective while writing — these are facts about the process that produced
the code, not about the tokens it left behind. No static analysis can recover
them.

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff, and each has a focused
follow-up issue rather than a sniff built under this documentation-only
standard:

- **Class with no corresponding test file** —
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128). A sniff on
  class declarations under the source directory can check that a companion
  `*Test.php` exists. This catches test *absence* (a visible end-state
  violation), though never test-first *order*.
- **Procedural code in source files** —
  [#129](https://github.com/mike-bronner/phpcs-rules/issues/129). The
  "only implement classes, never procedural code" bullet is directly
  token-visible: a sniff can flag any top-level executable statement in a
  source file that isn't part of a single OO declaration.

The semantic core of the standard — the TDD cycle itself, the two-perspective
discipline, complexity-guided test counts, and deferred DRYing — remains
enforced by code review.
