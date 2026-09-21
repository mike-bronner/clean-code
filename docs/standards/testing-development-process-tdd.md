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

## Enforceability — Tier 3 (the process), Tier 2 (two slices)

This is a development-*process* standard, and the process itself is enforced by
**code review and developer discipline**. Two things are exceptions, and both
describe the code's end state rather than the process that produced it: "only
implement classes, never procedural code" is enforced by the custom sniff
`CleanCode.Files.NoProceduralCode`, and a class existing with no test at all is
enforced by the custom sniff `CleanCode.Testing.RequireTestFile`. Both are
described below.

Everything else stays with the reviewer. A token-based PHPCS sniff inspects one
file's tokens in isolation at lint time. Whether a test was written *before* its
implementation, whether code was grown through Red/Green/Refactor cycles, or
whether the author held the right perspective while writing — these are facts
about the process that produced the code, not about the tokens it left behind.
No static analysis can recover them.

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff, and each got a focused issue
rather than a sniff built under this documentation-only standard:

- **Class with no corresponding test file** —
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128), **now
  enforced** by the custom sniff `CleanCode.Testing.RequireTestFile`. A sniff on
  class declarations under the source directory can check that a companion
  `*Test.php` exists. This catches test *absence* (a visible end-state
  violation), though never test-first *order*. It is described below.
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
  `CleanCode/ruleset.xml` already brings in. PSR-1 forbids only *mixing* a declaration
  with side effects, so a file that is nothing but procedural code declares no
  symbol and passes it silently. Nothing in Slevomat's standard speaks about a
  file's declaration count either.
- **Error severity, report-only.** Wrapping loose statements in a class
  decides which class, which method, and which visibility, so there is no
  mechanical rewrite to offer.
- **Boundaries** — the sniff never looks at the file's path; scoping is a
  ruleset concern. `CleanCode/ruleset.xml` restricts it to `src/` and `app/` with
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

### `CleanCode.Testing.RequireTestFile`

- **Detection** — a concrete class declared under a configured source directory
  is reported when no file matches the companion path its own location implies.
  The shipped mapping is the PSR-4 mirror: `src/Foo/Bar.php` looks for
  `tests/Foo/BarTest.php`, with `app/` as a second source root. The companion is
  named for the *file*, not for the class the file declares, which is what a
  PSR-4 autoloader keys on; the two disagreeing is a PSR-1/PSR-4 violation owned
  by the sniffs that carry that rule.
- **Four properties carry the whole mapping**, so a project retunes it rather
  than overriding the ruleset: `sourceDirectories` (the directory names that
  mark a source root, and the sniff's own scope), `testDirectory` (the test
  root, relative to the project root), `testPathTemplate` (`{path}` for the
  source-relative directory, `{name}` for the file's base name), and
  `excludePatterns` (fnmatch globs for framework scaffolding — migrations,
  service providers, config classes). `excludePatterns` ships empty: which
  scaffolding is exempt is a property of the application, not of the standard.
- **The expected path is a glob pattern, not a literal**, which is what keeps a
  non-mirrored layout down to a single lookup. A suite split into `Unit/` and
  `Feature/` sets `testDirectory` to `tests/*`. Exactly one `glob()` call is
  made per class declaration, and `*` never crosses a separator, so the cost is
  fixed by the configured pattern rather than by the size of the suite — there
  is no directory walk and no recursive search. The wildcards belong to the
  configured properties alone: the parts read off the filesystem — the
  directories above the source root, `{path}` and `{name}` — are quoted before
  they are substituted in, so a project checked out under a directory called
  `build[1]` resolves its companions from `build[1]/tests/` rather than from
  whatever `build1` might be.
- **Never flagged** — interfaces, traits and enums (the tokenizer spells them
  `T_INTERFACE`, `T_TRAIT` and `T_ENUM`, none of which the sniff registers),
  anonymous classes (`T_ANON_CLASS`, and no name for a test to be named after),
  abstract classes (exercised through their concrete subclasses; the one
  exemption read off the declaration rather than off the token), anything
  matching `excludePatterns`, any file with no source root on its path, and
  piped input, which has no location to resolve a companion against.
- **Warning severity, report-only.** The rule reads a project's layout off a
  configurable convention and cannot prove it guessed right, so a misread must
  not fail a build. The fix is writing the missing test, and no fixer can write
  it.
- **Existence only.** It says a file exists at the expected path. It does not
  say the test was written first, and it does not say the test asserts anything.

## What remains code review

Everything except those two slices. The TDD cycle itself, the two-perspective
discipline, complexity-guided test counts, and deferred DRYing leave no trace a
tokenizer can read, so a reviewer is the only enforcement there is. The
test-existence slice is narrow in exactly that way: a sniff can say only that
*a* test file exists — not that it was written first, nor that it asserts
anything meaningful.

The enforced slice is narrow in the same way: it says a source file's top level
holds one declaration and nothing else. Whether that declaration is a class
worth having, and whether the logic inside it was grown test-first, stays with
the reviewer.

How this repo applies the standard to its own sniffs — the fixture contract,
the Pest functional style, and the contract sweep (which every new sniff joins,
unless it is scoped by path) — is documented in
[CONTRIBUTING.md](../../CONTRIBUTING.md).
