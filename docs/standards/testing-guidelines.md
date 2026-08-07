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

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced
by a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time. Where a developer *started* writing tests, whether "Shameless Green" was
reached quickly, whether code reads well *for a human*, whether every scenario
has both success and failure coverage, and whether a mocked interface is one
the team "controls" — these are judgments about intent, process, and coverage
across files, not facts recoverable from a single file's token stream.

## Partial enforcement assessment

Two narrow slices **are** catchable by a sniff, and each has a focused
follow-up issue rather than a sniff built under this documentation-only
standard:

- **Reflection-based access to non-public methods in tests** —
  [#145](https://github.com/mike-bronner/phpcs-rules/issues/145). "Only test
  public methods" has one token-visible violation mechanism: using
  `ReflectionMethod` / `setAccessible(true)` in a test to invoke a
  protected/private member directly instead of going through the public API.
- **Mocking first-party classes in tests** —
  [#146](https://github.com/mike-bronner/phpcs-rules/issues/146). "Do not mock
  classes you control" is approximated by flagging mock creation
  (`createMock`, `Mockery::mock`, Laravel's `$this->mock`) whose class
  argument resolves to a configured first-party namespace prefix.

Adjacent slices already tracked elsewhere:

- **Source class with no corresponding test file** — covered by
  [#128](https://github.com/mike-bronner/phpcs-rules/issues/128) (opened from
  *Testing: Development Process (TDD)*), which catches test *absence* for the
  "always write unit and integration tests" bullet.
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
