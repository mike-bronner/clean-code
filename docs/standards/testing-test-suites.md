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
it sits in remains a judgment — but one part of it is not. A feature test must
not traverse the internet, and the raw primitives that can only traverse it are
written in the file that calls them. The custom sniff
**`CleanCode.Testing.NoInternetTraversal`**
([#149](https://github.com/mike-bronner/phpcs-rules/issues/149)) reports them
under a single `Found` code, naming the primitive that matched:

- `curl_init()`, `curl_exec()`, `fsockopen()` and `stream_socket_client()` —
  each one exists to open a connection, so the call alone is the violation and
  no argument is read;
- `file_get_contents()` whose first argument is one whole string literal whose
  text begins `http://` or `https://` (either case) — the function is otherwise
  ordinary, so the URL is what makes it a request;
- `new GuzzleHttp\Client` — resolved through the file's own namespace and `use`
  imports, so an import, an alias and the fully-qualified spelling all report
  while an unrelated `Client` from another namespace does not.

The sanctioned route is the `Http` facade with `Http::fake()`, which is
deliberately not a watched primitive. Only files whose path matches
`featureTestPatterns` are inspected — fnmatch globs defaulting to any path
holding a `tests/Feature` pair — so the same primitive in an integration test,
where the standard says it belongs, is left alone.

It reports **warnings, not errors**, and is **detection-only**: replacing a real
request with a fake means writing the fake — what to return, and for which URLs
— which is not recoverable from the call being replaced.

Seven boundaries come with it, and none is a defect to be fixed later:

- a request through an un-faked `Http` facade call is not flagged, because
  whether a fake is active is set up elsewhere and is not statically decidable;
- a request made through a service class the test calls carries no primitive of
  its own and needs project-wide symbol resolution;
- a variable URL (`file_get_contents($url)`) states nothing about where it
  points, and neither does a concatenation — the argument must be one whole
  literal;
- a URL whose scheme is spelled with escape sequences (`"\x68ttp://…"`) is not
  recognised; the two idiomatic spellings agree, since `'http://…'` and
  `"http://…"` hold identical characters between their delimiters;
- a URL literal split across physical lines is tokenized one token per line, and
  only a whole literal is read;
- `fsockopen()` and `stream_socket_client()` also address a `unix://` or
  `udg://` socket, which never leaves the machine, and are reported all the same
  — the standard names the two calls outright, and a raw socket opened by hand is
  not what a feature test should hold whichever transport it names;
- another HTTP client is outside the slice: the watched list is a constant, not a
  property, because the standard names Guzzle and a retunable list would make the
  rule mean something different in each project.

Whether a feature test that uses none of these still reaches the internet — and
whether every faked feature test has its unfaked integration twin — remains a
judgment, and stays with code review.

## Partial enforcement assessment

Each suite has a token-visible *content-mismatch* slice — code whose mere
presence in that suite's directory contradicts the suite's definition. Each got
a focused follow-up issue rather than a sniff built under this
documentation-only standard; one of the three has since shipped:

- **External concerns in `tests/Unit/`** —
  [#148](https://github.com/mike-bronner/phpcs-rules/issues/148). Database
  traits (`RefreshDatabase` and friends), facade fakes (`Http::fake`,
  `Queue::fake`, …), and HTTP-kernel test calls (`$this->get(`, …) are
  token-visible external concerns; a unit test concerns only the class under
  test.
- **Internet-traversing primitives in `tests/Feature/`** — *implemented*, as
  `CleanCode.Testing.NoInternetTraversal` (see Enforceability above). Raw
  `curl_*`/`fsockopen` calls, `file_get_contents('http…')`, and direct
  `GuzzleHttp\Client` instantiation are token-visible signals a feature test
  traverses the internet instead of faking it
  ([#149](https://github.com/mike-bronner/phpcs-rules/issues/149)).
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
