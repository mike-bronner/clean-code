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

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced
by a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Whether a test's subject is "only the class under test", whether a
feature test's traffic actually stays off the internet at runtime, and whether
every HTTP-faked feature test has an identical unfaked integration twin — these
are judgments about a test's semantic scope, its runtime behavior, and pairing
across files, not facts recoverable from a single file's token stream.

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

- **Bare directory-location / naming-prefix check** (test classes must live
  under `tests/Unit|Feature|Integration`) — presence in a suite directory says
  nothing about whether the file's *content* belongs there, which is the
  standard's actual rule; PHPUnit's suite configuration already owns the
  layout. The three content-vs-directory sniffs above are the meaningful
  slices.
- **Faked-feature-test ↔ unfaked-integration-twin pairing** — inherently
  cross-file; not recoverable from a single file's token stream.

The semantic core of the standard — what each suite is *for*, striving for
unit tests where possible, and keeping the faked/unfaked twin coverage in sync
— remains enforced by code review.
