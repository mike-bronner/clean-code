# Contributing

This package is a PHP_CodeSniffer standard (`composer.json` type
`phpcodesniffer-standard`). Custom sniffs live in the **CleanCode** standard;
the master ruleset consumers run is **`rules.xml`** at the package root.

Tests are written with **[Pest](https://pestphp.com/)**. There is one test
layout — the older `AbstractSniffUnitTest` harness under `CleanCode/Tests/` is
gone, and nothing lives beside the sniffs any more.

## Layout

```
rules.xml                                  # master ruleset — standards get wired in here
CleanCode/
├── ruleset.xml                            # the installable CleanCode standard
└── Sniffs/
    └── <Category>/<Name>Sniff.php         # one sniff per file
docs/standards/                            # one doc per clean-code standard
tests/
├── bootstrap.php                          # PHPCS test constants + ConfigDouble autoloading
├── Pest.php                               # Pest config; requires Helpers.php
├── Helpers.php                            # the sniff-driving helper functions
├── fixtures/
│   ├── <Name>Sniff/                       # per-sniff fixtures, named for the sniff class
│   └── _rulesets/<Standard>/              # fixtures for standards carried by several sniffs
├── Contract/                              # the generic three-fixture sweep
├── Standards/                             # per-sniff behaviour of the custom CleanCode sniffs
├── Rules/                                 # rules.xml's *configuration* of third-party sniffs
├── Ruleset/                               # third-party & custom sniffs as wired into rules.xml
└── Integration/                           # whole-ruleset behaviour, with its own fixtures/
```

### The fixture contract

Every sniff owns a directory under `tests/fixtures/` named for its **class short
name** — `CleanCode.Arrays.ArrayAccessors` → `ArrayAccessorsSniff/`. Inside it,
up to three fixtures carry fixed names:

| Fixture | Meaning | Required? |
|---|---|---|
| `passing.php` | code the sniff must leave alone | **always** |
| `failing.php` | code the sniff must flag | **always** |
| `autofixed.php` | `phpcbf`'s output for `failing.php` | fixable sniffs only |

All fixtures are `.php`. `passing.php` and `failing.php` are the floor — every
sniff carries both. Only `autofixed.php` is optional, and only because a
detection-only sniff has no safe mechanical rewrite to assert.

**`autofixed.php` is never a substitute for `passing.php`.** It is the fixer's
own output, so asserting the sniff is silent on it tests the fixer twice and the
compliant form never — and for a partial fixer it is not even clean
(`OneConditionPerLineSniff/autofixed.php` deliberately retains a non-fixable
violation).

Make `passing.php` *discriminating*: it should contain the constructs the sniff
registers on, in their compliant form, plus the near-miss shapes the sniff must
stay silent on. A compliant fixture that simply contains nothing the sniff looks
at will pass forever without asserting anything.

Anything beyond the three gets a **descriptive** name saying what it exercises —
`boundaries.php`, `marker-collision.php`, `after-open-tag-two-blanks.php` — plus
a `<name>.fixed.php` sibling if it has expected fixer output. Never a numeric
suffix.

A standard implemented by **several** sniffs at once (TypeHints, the operator
spacing pair, the naming casing conventions) has no single owning sniff, so its
fixtures live in `tests/fixtures/_rulesets/<Standard>/` under the same names.

Fixtures are never collected as tests: a PHPUnit `<directory>` defaults to
`suffix="Test.php"`, so only `*Test.php` files are loaded. That is what lets a
deliberately malformed fixture sit safely inside a suite directory. They are
also excluded from `composer lint` (`--ignore=*/fixtures/*`), since an
intentionally non-compliant fixture must not fail the PSR-12 self-lint.

### The helpers

`tests/Helpers.php` holds plain functions — not a base `TestCase` — because
nothing about driving a sniff is stateful across a test's lifecycle. The ones
you will reach for:

- `analyzeFixture($sniffCode, $fixture, $configure = null)` — run one fixture
  through a ruleset narrowed to one sniff, resolving the fixture directory from
  the sniff code. `$configure` receives the sniff instance so a test can set its
  public properties the way a consuming ruleset would.
- `analyzeRulesetFixture([$sniffCodes], $directory, $fixture)` — the
  `_rulesets/` equivalent, for a standard carried by several sniffs.
- `analyzeWithMasterRuleset($path)` — the *whole* ruleset, every sniff active.
  Use it when the point is how rules interact; scope the assertions to the
  sources under test so unrelated additions to `rules.xml` cannot break them.
- `buildRuleset()` — `[$config, $ruleset]`, for asserting a sniff is registered.
- `violationSourcesByLine()`, `violationCountsByLine()`, `violationTuples()`,
  `allViolationSourcesByLine()`, `violationFixableFlags()` — collapse PHPCS's
  nested `line => column => violations` structure into something assertable.
- `autofixedContents($file)` — run the fixer and return the result.

Every helper builds the master ruleset and *then* narrows `$ruleset->sniffs`,
rather than restricting PHPCS via `$config->sniffs`. This is load-bearing: under
`PHP_CODESNIFFER_IN_TESTS` a `$config->sniffs` restriction makes `Ruleset` skip
parsing `rules.xml` altogether — which is what pulls the custom CleanCode sniffs
in and what applies the `<properties>` configured there.

## Adding a new sniff

1. **Create the sniff** at `CleanCode/Sniffs/<Category>/<Name>Sniff.php`, in the
   namespace `MikeBronner\CleanCode\Sniffs\<Category>`, implementing
   `PHP_CodeSniffer\Sniffs\Sniff`. Its code becomes
   `CleanCode.<Category>.<Name>`. Use
   `CleanCode/Sniffs/Debug/DisallowDebugFunctionsSniff.php` as the template.
   Sniffs in the standard's `Sniffs/` directory are included automatically —
   no per-sniff registration in `CleanCode/ruleset.xml` is needed.
2. **Add its fixtures** at `tests/fixtures/<Name>Sniff/`, following the contract
   above. Compliant and violating code go in **separate files**, never one.
3. **Add it to the contract sweep** in `tests/Contract/SniffContractTest.php` —
   one entry in `SWEPT_SNIFFS` if it reports errors, or in
   `SWEPT_WARNING_SNIFFS` if its violations are warnings (the failing-fixture
   assertion reads the matching violation list; both lists feed the passing and
   registration datasets, so the floor cannot be half-applied). Add it to
   `autofixable sniffs` too, and, if its fixer is total,
   `sniffs whose fixer resolves every violation`. That alone gives it the
   generic passing/failing/autofix/idempotence coverage.
4. **Add its behaviour test** at `tests/Standards/<Name>Test.php`, asserting the
   exact lines, columns, and violation sources — see
   `tests/Standards/NotOperatorSpacingTest.php` for the simple shape and
   `tests/Standards/ArrayAccessorsTest.php` for a thoroughly documented one.
   Tests are auto-discovered; nothing to register.
5. **Wire third-party rules into `rules.xml`** when a standard is enforced by an
   existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`. Custom
   CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line. Pin the configured
   thresholds/behaviour with a test in `tests/Rules/` — see
   `tests/Rules/LineLengthRulesTest.php` as the template.
6. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there.

## Writing the tests

Use Pest's functional style — `it('does the thing', function () { … })` — not a
`TestCase` subclass. Where several fixtures exercise the same assertion, use a
dataset (`->with([...])`) rather than copy-pasting the test.

Say *why* in a docblock above the test whenever the assertion encodes a
judgement call: which of two overlapping sniffs owns a diagnostic, a deliberate
false positive left in place, a tokenizer defect being pinned rather than worked
around. Several tests here exist purely to stop someone "fixing" behaviour that
is intentional, and they are only useful if they explain themselves.

## Running the checks

```bash
composer install
composer test     # the full Pest suite
composer lint     # PSR-12 self-lint of the sniff/test code (fixtures excluded)

vendor/bin/pest --testsuite=Standards      # one suite
vendor/bin/pest --filter='flags every'     # one test
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```
