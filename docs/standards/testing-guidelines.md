# Testing: Guidelines

## Standard

- Start where you would start writing code; the first test doesn't have to be
  elegant or correct — just get started.
- Goal: get to "Shameless Green" quickly (ugly code that satisfies all tests).
- Code is written for understanding, not extreme pattern adherence; the human
  is the focus.
- Always write unit and integration tests, testing success and failure for
  each scenario.
- Only test public methods; cover protected/private methods through the public
  ones. Uncovered non-public methods are inaccessible (remove) or the tests
  aren't comprehensive.
- Tests document functionality through careful naming.
- Mock external interfaces you don't control (test success and failure), but
  also write integration tests so mocks don't go stale. Do not mock classes
  you control.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3, with two partial rules

This is an architectural / semantic / process standard. Its **core** is
enforced by **code review and developer discipline**; two narrow slices are
enforced by the custom sniffs `CleanCode.Testing.NoReflectionAccess` and
`CleanCode.Testing.NoFirstPartyMocks`, described below.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Where a developer *started* writing tests, whether "Shameless Green" was
reached quickly, whether code reads well *for a human*, whether every scenario
has both success and failure coverage, and whether a mocked interface is one
the team "controls" — these are judgements about intent, process, and coverage
across files, not facts recoverable from a single file's token stream.

## The rule: `CleanCode.Testing.NoReflectionAccess`

"Only test public methods" has one token-visible violation mechanism: reaching
a protected or private member from a test with Reflection instead of going
through the public API. The sniff reports that mechanism, as a **warning**,
detection only — replacing a Reflection call with public-API coverage is a
redesign of the test, not a mechanical rewrite ([#145](https://github.com/mike-bronner/phpcs-rules/issues/145)).

It fires only inside test files, and reports two things:

| Shape | Examples |
|---|---|
| Instantiating a member-specific Reflection class | `new ReflectionMethod(…)`, `new ReflectionProperty(…)`, `new \ReflectionMethod(…)` |
| Calling a Reflection member that reaches a named member | `->setAccessible(…)`, `->getMethod(…)`, `->getProperty(…)`, `->invoke(…)`, `->invokeArgs(…)`, and the `?->` and `::` spellings of each |

`new ReflectionClass(…)` on its own is **not** reported. Reading a class's
metadata is legitimate; it becomes this standard's violation only once
`getMethod()` or `getProperty()` narrows it to one member, which the second row
covers.

Three public properties configure it from a consuming ruleset:

```xml
<rule ref="CleanCode.Testing.NoReflectionAccess">
    <properties>
        <property name="testFilePatterns" type="array">
            <element value="*/tests/*"/>
            <element value="*/Tests/*"/>
            <element value="*Test.php"/>
        </property>
        <property name="reflectionClasses" type="array">
            <element value="ReflectionMethod"/>
            <element value="ReflectionProperty"/>
        </property>
        <property name="reflectionMembers" type="array">
            <element value="getMethod"/>
            <element value="getProperty"/>
            <element value="invoke"/>
            <element value="invokeArgs"/>
            <element value="setAccessible"/>
        </property>
    </properties>
</rule>
```

The values above are the shipped defaults. `testFilePatterns` holds `fnmatch`
globs matched against the file's path (with `\` normalised to `/`), and it
gates the whole rule: a file matching none of them is never inspected, so
production code is out of reach by construction. The match is case-sensitive,
which is why both `tests` and `Tests` are listed — folding case would make
`*Test.php` swallow `latest.php`.

### Known limits

Both are by design, and both are why the rule warns rather than errors:

- It catches the Reflection **mechanism** only, not the intent. A test that
  reaches non-public state another way — a `\Closure::bind()` rebind, a
  subclass widening visibility, a debug accessor on the class under test — is
  invisible to it.
- `reflectionMembers` matches on member **name**, not on receiver type, which a
  single-file token scan cannot resolve. `$container->invoke($job)` therefore
  reports even though no Reflection is involved.

Legitimate Reflection in a test — framework plumbing, data-provider metadata —
takes the ordinary per-line suppression:

```php
// phpcs:ignore CleanCode.Testing.NoReflectionAccess.Found
$method = new ReflectionMethod(Calculator::class, 'applyDiscount');
```

A project that hits the second limit often can narrow `reflectionMembers`
instead.

## The rule: `CleanCode.Testing.NoFirstPartyMocks`

"Do not mock classes you control" has one token-visible approximation: a
mock-creation call whose class argument resolves into a namespace root the
project owns. The sniff reports that shape, as a **warning**, detection only —
replacing a mock of a class you own with the real collaborator is a redesign of
the test, not a mechanical rewrite ([#146](https://github.com/mike-bronner/phpcs-rules/issues/146)).

It fires only inside test files, and only once configured. These are the
mock-creation calls it recognises:

| Source | Calls |
|---|---|
| PHPUnit | `$this->createMock(…)`, `$this->createPartialMock(…)`, `$this->getMockBuilder(…)` |
| Mockery | `Mockery::mock(…)`, `Mockery::spy(…)` |
| Laravel test helpers | `$this->mock(…)`, `$this->partialMock(…)`, `$this->spy(…)` |

The first argument is resolved to a fully-qualified name from the file's own
`namespace` declaration and `use` imports, so every spelling of the same class
lands on the same answer:

| Written | Resolved (in `namespace App\Tests\Unit`, with `use App\Models\User`) |
|---|---|
| `User::class` | `App\Models\User` — through the import |
| `Models\Comment::class` | `App\Models\Comment` — every segment past the alias, given `use App\Models` |
| `\App\Models\User::class` | `App\Models\User` — already qualified |
| `namespace\Support\Clock::class` | `App\Tests\Unit\Support\Clock` — relative |
| `Support\Clock::class` | `App\Tests\Unit\Support\Clock` — current namespace |
| `'App\Models\User'` | `App\Models\User` — a string is never resolved through imports |
| `'App\\Models\\User'` | `App\Models\User` — a doubled separator is an escape in either quote style |
| `self::class`, `static::class` | `App\Tests\Unit\UserTest` — the class the call is written in |
| `parent::class` | the `extends` clause of that class, resolved like any other written name |

`self`, `static` and `parent` matter more than they look:
`$this->createPartialMock(static::class, [...])` is the idiomatic way to
partial-mock the class a test file is about, so leaving it unresolved would
miss the commonest first-party partial mock there is.

A `use function` or `use const` import brings no class into scope and so never
enters that map — written on the statement, where it binds every clause, or on
one clause of a group (`use App\{Order, function build};`), where it binds that
clause alone.

A reference the file's own tokens cannot resolve — a variable, a call, a
concatenation, a constant that is not `::class` — is left alone rather than
guessed at.

Three public properties configure it from a consuming ruleset:

```xml
<rule ref="CleanCode.Testing.NoFirstPartyMocks">
    <properties>
        <property name="firstPartyNamespaces" type="array">
            <element value="App"/>
        </property>
        <property name="testFilePatterns" type="array">
            <element value="*/tests/*"/>
            <element value="*/Tests/*"/>
            <element value="*Test.php"/>
        </property>
        <property name="mockCreators" type="array">
            <element value="createMock"/>
            <element value="createPartialMock"/>
            <element value="getMockBuilder"/>
            <element value="mock"/>
            <element value="partialMock"/>
            <element value="spy"/>
        </property>
    </properties>
</rule>
```

`firstPartyNamespaces` ships **empty on the sniff class**: nothing in one file
says which roots a project owns, so an unconfigured sniff is a no-op rather
than a guesser. This package's own `rules.xml` configures `App`, the root of
the Laravel layout these standards are written against; a project with
different roots replaces the element list, and a class under **any** listed
root is first-party. Roots are compared segment-wise and case-insensitively, so
`App` covers `App\Models\User` and never `Application\Order`.

`testFilePatterns` behaves exactly as it does for `NoReflectionAccess` above,
and gates this rule the same way.

### Known limits

All three are by design, and the first two are why the rule warns rather than
errors:

- `mockCreators` matches on member **name**, not on receiver type, which a
  single-file token scan cannot resolve. `$surveillance->spy(User::class)`
  therefore reports even though no mocking library is involved.
- A **facade or contract that wraps a genuinely external service** lives in the
  project's own namespace and so reports, even though the thing being mocked is
  external. This is the gray area the standard's own wording leaves open.
- `self`, `static` and `parent` resolve **only inside a named class**. In a
  trait or an anonymous class they name a class the file never writes down, and
  `parent` in a class with no `extends` names nothing at all, so each of those
  stays silent. `static` resolves to the class the call is written in; a
  subclass binding it to something else at run time is beyond a single-file
  scan.

The first two take the ordinary per-line suppression:

```php
// phpcs:ignore CleanCode.Testing.NoFirstPartyMocks.Found
$gateway = $this->createMock(PaymentGatewayContract::class);
```

A project that hits either often can narrow `mockCreators` or the namespace
roots instead.

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff, and both are now implemented:

- **Reflection-based access to non-public methods in tests** — [#145](https://github.com/mike-bronner/phpcs-rules/issues/145),
  **implemented** as `CleanCode.Testing.NoReflectionAccess`, above.
- **Mocking first-party classes in tests** —
  [#146](https://github.com/mike-bronner/phpcs-rules/issues/146),
  **implemented** as `CleanCode.Testing.NoFirstPartyMocks`, above.

Adjacent slices already tracked elsewhere:

- **Source class with no corresponding test file** — covered by
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128) (opened from
  *Testing: Development Process (TDD)*), which catches test *absence* for the
  "always write unit and integration tests" bullet. Now implemented as
  `CleanCode.Testing.RequireTestFile`; see
  [testing-development-process-tdd.md](testing-development-process-tdd.md).
- **Careful test naming** — method naming belongs to the naming standards:
  casing is already enforced as *Naming: Casing Conventions*
  ([#22](https://github.com/mike-bronner/phpcs-rules/issues/22)) and the wider
  method-naming standard is tracked separately
  ([#61](https://github.com/mike-bronner/phpcs-rules/issues/61)). A bare
  prefix check adds nothing PHPUnit doesn't already require, and "careful"
  naming is a judgement about whether the name describes the behaviour — not
  a shape a sniff can read.

The assessment is recorded on
[#54](https://github.com/mike-bronner/phpcs-rules/issues/54).

## What remains code review

The semantic core of the standard — start-anywhere pragmatism, "Shameless
Green", human-focused code, success-and-failure coverage per scenario, and
keeping integration tests alongside mocks so mocks don't go stale — remains
enforced by code review. The review obligation is concrete: whenever a change
adds or modifies a test, the reviewer confirms that each scenario the change
covers has both a success and a failure case, that new coverage goes through
the public API rather than reaching into a protected or private method, and
that any newly mocked collaborator is genuinely external — with an integration
test alongside it that would fail if the real interface drifted from the mock.

Two halves of that obligation are now partly automated: `NoReflectionAccess`
reports the Reflection route into a non-public member, and `NoFirstPartyMocks`
reports a mock of a class in the project's own namespace. Neither says anything
about the other routes into a non-public member, nor about whether an
integration test sits alongside a mock, so the reviewer still owns those.
