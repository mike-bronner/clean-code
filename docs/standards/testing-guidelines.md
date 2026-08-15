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

## Enforceability — Tier 3, with one partial rule

This is an architectural / semantic / process standard. Its **core** is
enforced by **code review and developer discipline**; one narrow slice is
enforced by the custom sniff `CleanCode.Testing.NoReflectionAccess`, described
below.

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
        <property name="testFilePatterns" type="array" value="*/tests/*,*/Tests/*,*Test.php"/>
        <property name="reflectionClasses" type="array" value="ReflectionMethod,ReflectionProperty"/>
        <property name="reflectionMembers" type="array" value="getMethod,getProperty,invoke,invokeArgs,setAccessible"/>
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

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff. The first is now the rule
above; the second is a focused follow-up issue rather than a sniff built under
this standard:

- **Reflection-based access to non-public methods in tests** — [#145](https://github.com/mike-bronner/phpcs-rules/issues/145),
  **implemented** as `CleanCode.Testing.NoReflectionAccess`, above.
- **Mocking first-party classes in tests** —
  [#146](https://github.com/mike-bronner/phpcs-rules/issues/146). "Do not mock
  classes you control" is approximated by flagging mock creation
  (`createMock`, `Mockery::mock`, Laravel's `$this->mock`) whose class
  argument resolves to a configured first-party namespace prefix.

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

The public-API half of that obligation is now partly automated: the sniff above
reports the Reflection route into a non-public member. It reports nothing about
the other routes, so the reviewer still owns them.
