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

## Enforceability — Tier 3 (the process), Tier 2 (one slice)

This is a development-*process* standard, and the process itself is enforced by
**code review and developer discipline**. One bullet is an exception: "only
implement classes, never procedural code" describes the code's end state rather
than the process that produced it, and is enforced by the custom sniff
`CleanCode.Files.NoProceduralCode` (see below).

Everything else stays with the reviewer. A token-based PHPCS sniff inspects one
file's tokens in isolation at lint time. Whether a test was written *before* its
implementation, whether code was grown through Red/Green/Refactor cycles, or
whether the author held the right perspective while writing — these are facts
about the process that produced the code, not about the tokens it left behind.
No static analysis can recover them.

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff, and each has a focused issue
rather than a sniff built under this documentation-only standard:

- **Class with no corresponding test file** —
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128). A sniff on
  class declarations under the source directory can check that a companion
  `*Test.php` exists. This catches test *absence* (a visible end-state
  violation), though never test-first *order*. Still queued.
- **Procedural code in source files** —
  [#129](https://github.com/mike-bronner/phpcs-rules/issues/129), **now
  enforced** by the custom sniff `CleanCode.Files.NoProceduralCode`. The
  "only implement classes, never procedural code" bullet is directly
  token-visible, and is described below.

### `CleanCode.Files.NoProceduralCode`

- **Detection** — the file's top level may hold only a `declare`, a
  `namespace`, `use` imports, comments, attributes, class modifiers, and
  exactly one `class`, `interface`, `trait`, or `enum` declaration. Every
  other top-level construct — a call, an assignment, a control structure, a
  standalone `function`, a `const`, a `return`, markup — is reported once, at
  its own line, as `ProceduralStatement`. Each declaration after the first is
  reported as `MultipleDeclarations`.
- **Stricter than `PSR1.Files.SideEffects`**, which the PSR12 reference in
  `rules.xml` already brings in. PSR-1 forbids only *mixing* a declaration
  with side effects, so a file that is nothing but procedural code declares no
  symbol and passes it silently. Nothing in Slevomat's standard speaks about a
  file's declaration count either.
- **Error severity, report-only.** Wrapping loose statements in a class
  decides which class, which method, and which visibility, so there is no
  mechanical rewrite to offer.
- **Boundaries** — the sniff never looks at the file's path; scoping is a
  ruleset concern. `rules.xml` restricts it to `src/` and `app/` with
  `<include-pattern>`, because entry points (`public/index.php`, `artisan`),
  config files (a top-level `return []`), route files, and pre-Laravel-9-style
  migrations (`return new class …`) are legitimately procedural and all of
  them live outside those two directories. A project that keeps its classes
  elsewhere adds its own `<include-pattern>` for that path. A file that
  declares nothing *and* executes nothing — an empty file, a comment-only
  placeholder — holds no procedural code to point at and is left alone; the
  absence of a class is what [#128](https://github.com/mike-bronner/phpcs-rules/issues/128)
  is about, not this slice. A closing tag is left to
  `PSR12.Files.ClosingTag`, which owns it; only the markup after one is
  reported.

## What remains code review

Everything except those two slices. The TDD cycle itself, the two-perspective
discipline, complexity-guided test counts, and deferred DRYing leave no trace a
tokenizer can read, so a reviewer is the only enforcement there is. Even once
[#128](https://github.com/mike-bronner/phpcs-rules/issues/128) lands, a sniff
can say only that *a* test file exists — not that it was written first, nor
that it asserts anything meaningful.

The enforced slice is narrow in the same way: it says a source file's top level
holds one declaration and nothing else. Whether that declaration is a class
worth having, and whether the logic inside it was grown test-first, stays with
the reviewer.

How this repo applies the standard to its own sniffs — the fixture contract,
the Pest functional style, and the contract sweep (which every new sniff joins,
unless it is scoped by path) — is documented in
[CONTRIBUTING.md](../../CONTRIBUTING.md).
