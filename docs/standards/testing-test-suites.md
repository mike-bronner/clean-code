# Testing: Test Suites

## Standard

- **Unit Tests**: concern only the class under test; rare in Laravel (most
  classes have external concerns), but strive for them as they are fastest.
- **Feature Tests**: use more than internal methods (database, other classes,
  HTTP, WebSockets) but do NOT traverse the internet. Third-party APIs are
  tested here via HTTP fakes, with an identical integration test that does not
  use fakes.
- **Integration Tests**: dedicated to requests that test external dependencies
  over the internet.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 core, two enforced slices

The standard's core is architectural / semantic, and stays enforced by **code
review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Whether a test's subject is "only the class under test", whether a
feature test's traffic actually stays off the internet at runtime, and whether
every HTTP-faked feature test has an identical unfaked integration twin — these
are judgments about a test's semantic scope, its runtime behavior, and pairing
across files, not facts recoverable from a single file's token stream.

Two slices are not judgments, and both **are** enforced. The first: a test class
names its suite twice — once in the directory it sits in, once in its declared
namespace — and the two can contradict each other. A contradiction is a fact
about the file. The custom sniff **`CleanCode.Testing.TestSuiteNamespace`**
([#60](https://github.com/mike-bronner/phpcs-rules/issues/60)) reports it, in
both directions:

- `NamespaceMismatch` — a test class under a suite directory whose namespace
  names a different suite, or none;
- `DirectoryMismatch` — a test class declared in a suite namespace that does not
  live under the matching suite directory.

Both sides are read *relative to the test root*: the suite is the segment
directly below `tests`, and the root taken is the one closest to the file, so an
unrelated business-domain namespace such as `App\Domain\Feature\Toggle` is never
flagged. A class that declares no namespace, one whose namespace names no test
root at all, piped `STDIN` input, and non-test declarations under a suite
directory (traits, interfaces, abstract test cases, shared helpers) are all left
alone. The test root, the suite names, the class-name suffix and the recognized
base classes are configurable properties.

It reports **warnings, not errors**, and is **detection-only**: reconciling a
mismatch means moving the file or renaming its namespace, and which one is
correct depends on the project's layout rather than on anything in the file, so
there is no fixer.

This is the layout half only. Whether the *code* in a test belongs in the suite
it sits in remains a judgment — but one slice of it is token-visible too, and is
enforced by a second sniff.

### External concerns in `tests/Unit/`

A unit test concerns only the class under test. Whether it really does is a
judgment; three of the ways a Laravel test reaches past its subject are not, and
`CleanCode.Testing.UnitTestExternalConcerns`
([#148](https://github.com/mike-bronner/phpcs-rules/issues/148)) reports those:

- `DatabaseTrait` — a `use` of `RefreshDatabase`, `DatabaseMigrations`,
  `DatabaseTransactions` or `LazilyRefreshDatabase`, in any spelling a `use`
  statement takes (a plain import, a group import, or the trait use inside the
  class body).
- `FacadeFake` — a `fake()` call on `Http`, `Event`, `Queue`, `Bus`, `Storage`,
  `Notification` or `Mail`, which doubles out a whole external subsystem.
- `HttpRequest` — an HTTP-kernel request call on the test case: `$this->get(`,
  `$this->getJson(`, `$this->post(`, `$this->postJson(`, `$this->put(`,
  `$this->putJson(`, `$this->patch(`, `$this->patchJson(`, `$this->delete(` or
  `$this->deleteJson(`.

Each one says the test exercises the database, the HTTP layer, or another
external concern, which is what `tests/Feature/` is for — and the message points
there.

Only files under the configurable `unitTestPath` (default `tests/Unit/`) are
read at all: the same tokens in a feature test are exactly what that suite is
for. The path is matched as whole segments and anchored on the test root
*closest to the file*, so a checkout living under an unrelated `tests/Unit/`
directory does not pull the whole project into scope, and `tests/UnitOfWork/` is
not the unit suite.

It reports **warnings, not errors**, and is **detection-only**: the fix is
moving the test to `tests/Feature/`, or rewriting it to stop reaching outside
its subject, and neither is a mechanical rewrite. A flagged line can be
suppressed with an ordinary `phpcs:ignore` — `$this->get(...)` can be a genuine
userland method on a project's own test case.

Known limits, all deliberate:

- **Intent is not read.** A unit test that couples to a live dependency through
  plain constructor injection carries none of these traces and is not flagged.
- **Names are matched, not resolved.** A project's own class called `Http`, or
  its own trait called `RefreshDatabase`, reads as the Illuminate one; an
  aliased import does not.
- **The request-method list is #148's own enumeration**, taken in both the plain
  and `Json` spelling of each verb it names. Laravel is not a dependency of this
  package, so the rest of `MakesHttpRequests` (`options`, `head`, `call`,
  `json`) is not enumerated against its source and is left out rather than
  guessed at.
- **Only `fake` is read on a facade**, not `fakeSequence` — that one is #150's
  subject.

## Partial enforcement assessment

Each suite has a token-visible *content-mismatch* slice — code whose mere
presence in that suite's directory contradicts the suite's definition. Each
slice carries a focused issue of its own; the first is now built, and the other
two remain follow-ups:

- **External concerns in `tests/Unit/`** — *implemented*, as
  `CleanCode.Testing.UnitTestExternalConcerns`
  ([#148](https://github.com/mike-bronner/phpcs-rules/issues/148)). See
  "External concerns in `tests/Unit/`" under Enforceability above.
- **Internet-traversing primitives in `tests/Feature/`** —
  [#149](https://github.com/mike-bronner/phpcs-rules/issues/149). Raw
  `curl_*`/`fsockopen` calls, `file_get_contents('http…')`, and direct
  `GuzzleHttp\Client` instantiation are token-visible signals a feature test
  traverses the internet instead of faking it.
- **HTTP fakes in `tests/Integration/`** —
  [#150](https://github.com/mike-bronner/phpcs-rules/issues/150).
  `Http::fake(` / `Http::fakeSequence(` inside an integration test doubles out
  the very external dependency the suite exists to exercise.

Considered and declined:

- **Namespace-vs-directory consistency** — *implemented*, as
  `CleanCode.Testing.TestSuiteNamespace` (see Enforceability above). This is not
  the bare directory-location check this section previously declined. That one
  asked whether a file's *content* belonged in the directory holding it, which
  presence alone cannot answer; this one compares two statements the file makes
  about its own suite and reports only when they contradict each other.
- **Faked-feature-test ↔ unfaked-integration-twin pairing** — inherently
  cross-file; not recoverable from a single file's token stream.

The semantic core of the standard — what each suite is *for*, striving for
unit tests where possible, and keeping the faked/unfaked twin coverage in sync
— remains enforced by code review.
