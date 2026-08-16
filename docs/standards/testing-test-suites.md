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

## Enforceability — Tier 3 core, one enforced slice

The standard's core is architectural / semantic, and stays enforced by **code
review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Whether a test's subject is "only the class under test", whether a
feature test's traffic actually stays off the internet at runtime, and whether
every HTTP-faked feature test has an identical unfaked integration twin — these
are judgments about a test's semantic scope, its runtime behavior, and pairing
across files, not facts recoverable from a single file's token stream.

One slice is not a judgment, and it **is** enforced: a test class names its
suite twice — once in the directory it sits in, once in its declared namespace —
and the two can contradict each other. A contradiction is a fact about the file.
The custom sniff **`CleanCode.Testing.TestSuiteNamespace`**
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
it sits in remains a judgment, and stays with code review.

## Partial enforcement assessment

Each suite has a token-visible *content-mismatch* slice — code whose mere
presence in that suite's directory contradicts the suite's definition. Each
slice has a focused follow-up issue rather than a sniff built under this
documentation-only standard:

- **External concerns in `tests/Unit/`** —
  [#148](https://github.com/mike-bronner/phpcs-rules/issues/148). Database
  traits (`RefreshDatabase` and friends), facade fakes (`Http::fake`,
  `Queue::fake`, …), and HTTP-kernel test calls (`$this->get(`, …) are
  token-visible external concerns; a unit test concerns only the class under
  test.
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
