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

## Enforceability — Tier 3 core, four enforced slices

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
it sits in remains a judgment — but two slices of it are token-visible too, and
each is enforced by a sniff of its own.

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
- **Only `$this` is read as the test case.** A request method reached through
  any other variable is a call on some object the sniff cannot resolve, and is
  not flagged. That comparison is case-sensitive, unlike every other name here:
  PHP resolves variable names case-sensitively, so `$This` is a different
  variable, while `HTTP::FAKE()` is the same call as `Http::fake()` and does
  report.
- **The request-method list is #148's own enumeration**, taken in both the plain
  and `Json` spelling of each verb it names. Laravel is not a dependency of this
  package, so the rest of `MakesHttpRequests` (`options`, `head`, `call`,
  `json`) is not enumerated against its source and is left out rather than
  guessed at.
- **Only `fake` is read on a facade**, not `fakeSequence` — that one is #150's
  subject.

### Internet-traversing primitives in `tests/Feature/`

A feature test must
not traverse the internet, and the raw primitives that can only traverse it are
written in the file that calls them. The custom sniff
**`CleanCode.Testing.NoInternetTraversal`**
([#149](https://github.com/mike-bronner/phpcs-rules/issues/149)) reports them
under a single `Found` code, naming the primitive that matched:

- `curl_init()`, `curl_exec()`, `fsockopen()` and `stream_socket_client()` —
  each one exists to open a connection, so the call alone is the violation and
  no argument is read beyond whether the line calls the function at all;
- `file_get_contents()` whose filename argument is one whole string literal
  whose text begins `http://` or `https://` (either case) — the function is
  otherwise ordinary, so the URL is what makes it a request. The filename is the
  first argument when the call passes it positionally and the argument labelled
  `filename:` when the call names its arguments, wherever in the list it stands;
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

Eight boundaries come with it, and none is a defect to be fixed later:

- a primitive named without being called, or called without being named, is not
  flagged: `curl_init(...)` builds a Closure and opens nothing, while the line
  that later invokes that Closure (`$open()`), a variable function
  (`$fn = 'curl_init'; $fn();`) and `call_user_func('curl_init')` are real calls
  whose name is a variable or a string — what is read is a written name standing
  in call position;
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
presence in that suite's directory contradicts the suite's definition. Each
slice carries a focused issue of its own, and all three are now built:

- **External concerns in `tests/Unit/`** — *implemented*, as
  `CleanCode.Testing.UnitTestExternalConcerns`
  ([#148](https://github.com/mike-bronner/phpcs-rules/issues/148)). See
  "External concerns in `tests/Unit/`" under Enforceability above.
- **Internet-traversing primitives in `tests/Feature/`** — *implemented*, as
  `CleanCode.Testing.NoInternetTraversal`
  ([#149](https://github.com/mike-bronner/phpcs-rules/issues/149)). See
  "Internet-traversing primitives in `tests/Feature/`" under Enforceability
  above.

The third is described here rather than under Enforceability:

- **HTTP fakes in `tests/Integration/`** — *implemented*, as the custom sniff
  **`CleanCode.Testing.NoHttpFakesInIntegrationTests`**
  ([#150](https://github.com/mike-bronner/phpcs-rules/issues/150)). An HTTP
  double inside an integration test doubles out the very external dependency the
  suite exists to exercise, and installing one is token-visible. It reports two
  shapes:

  - `FakedHttpClient` — `Http::fake()`, `Http::fakeSequence()` and
    `Http::preventStrayRequests()` on the `Http` facade. The third is the one
    the standard's own wording turns on: an integration test *is* the stray
    request.
  - `MockedHttpClient` — a `createMock()` or `mock()` call whose first argument
    names `GuzzleHttp\Client` or a class under `Illuminate\Http\Client`, which
    doubles out the same dependency without going through the facade.

  The receiver is compared on its trailing namespace segment, so `Http`,
  `\Http` and `Illuminate\Support\Facades\Http` all read as the facade while
  `Https` and `ApiHttp` do not. A *qualified* mocked class must equal a
  configured client or sit beneath it, so a project's own `App\Support\Client`
  is left alone; an *unqualified* one is matched on the configured client's
  trailing segment, because one file's tokens cannot say what its imports bind.
  The suite directory, the faked methods, the mock creators and the client list
  are all configurable properties.

  Six boundaries are by design, and each is a limit of a single-file token scan:
  a fake installed in a shared base class or trait is invisible; an instance-side
  `$this->http->fake()` is not the static facade call and is left alone; suite
  *intent* is not read at all, so an "integration" test that never calls the
  external service is not flagged (test absence is
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128)'s territory);
  an aliased import is not resolved, while an unrelated class named `Client` is
  reported; an imported `Illuminate\Http\Client\Factory` written as
  `Factory::class` is not; and the mock creators are matched on member name
  rather than on receiver type.

  It reports **warnings, not errors**, and is **detection-only**: removing a
  fake from an integration test means either deleting coverage or moving the
  test to the feature suite, and only the project says which, so there is no
  fixer.

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
