# Contributing

This package is a PHP_CodeSniffer standard (`composer.json` type
`phpcodesniffer-standard`). Custom sniffs live in the **CleanCode** standard;
the master ruleset consumers run is **`rules.xml`** at the package root.

Tests are written with **[Pest](https://pestphp.com/)** and live under `tests/`.
New tests never use PHP_CodeSniffer's own `AbstractSniffUnitTest` harness: it
derives the fixture path from the test class itself — `<testsDir>/<Category>/
<Name>UnitTest.*`, one flat set of siblings — and forces the fixer expectation
to `<input>.fixed`. Neither can express the fixture layout below, which gives a
sniff its own directory and fixed names inside it. Tests here drive the real
`phpcs`/`phpcbf` through the helpers in `tests/Helpers.php` instead.

Two files still extend that harness and are the only exception:
`CleanCode/Tests/DeadCode/UnusedPrivateElementsUnitTest.php` and
`CleanCode/Tests/Operators/DisallowNewlineAroundEvaluativeOperatorsUnitTest.php`,
with their `.inc` fixtures beside them (and, for the operators one, an
`.inc.fixed`). No `<testsuite>` in `phpunit.xml.dist` covers `CleanCode/Tests/`,
so `composer test` never collects them — they are dead weight awaiting deletion,
not a second convention. Both
sniffs they name still have live assertions under `tests/`
(`tests/Ruleset/NoDeadCodeRulesetTest.php` and
`tests/Integration/OperatorRulesIntegrationTest.php`), so deleting the pair
loses no coverage. Add nothing to `CleanCode/Tests/`.

## Layout

```
rules.xml                                  # master ruleset — standards get wired in here
CleanCode/
├── ruleset.xml                            # the installable CleanCode standard
├── Sniffs/
│   └── <Category>/<Name>Sniff.php         # one sniff per file
└── Tests/                                 # legacy AbstractSniffUnitTest pair — uncollected, do not extend
docs/standards/                            # one doc per clean-code standard
docs/phpmd/                                # one doc per replicated PHPMD rule
tests/
├── bootstrap.php                          # PHPCS test constants + ConfigDouble autoloading; loads Sniffs.php
├── Sniffs.php                             # the sniff enumerations more than one suite reads
├── Pest.php                               # Pest config; requires Helpers.php
├── Helpers.php                            # the sniff-driving helper functions
├── fixtures/
│   ├── <Name>Sniff/                       # per-sniff fixtures, named for the sniff class
│   └── _rulesets/<Standard>/              # fixtures for standards carried by several sniffs
├── Contract/                              # the generic three-fixture sweep
├── Standards/                             # a custom sniff's own behaviour — one file per sniff
├── Rules/                                 # four older files doing tests/Ruleset/'s job — closed to new work
├── Ruleset/                               # a standard as rules.xml wires and configures it, plus its own fixtures/
└── Integration/                           # whole-ruleset behaviour, with its own fixtures/
```

### How a test gets collected

`composer test` runs Pest against `phpunit.xml.dist`, which declares one
`<testsuite>` per suite directory — `Contract`, `Integration`, `Rules`,
`Ruleset`, `Standards` — each pointing at `tests/<Name>`. A `<directory>`
defaults to `suffix="Test.php"`, so a file is collected when it sits in one of
those five directories **and** its name ends `Test.php`. Nothing else registers
a test, which is also why `CleanCode/Tests/` never runs: no `<testsuite>` names
it.

Put a test file anywhere else under `tests/` and the failure is **silent** — the
suite still reports green, having never loaded it. A new suite directory needs
its own `<testsuite>` entry before anything inside it runs.

`composer.json`'s `autoload-dev` PSR-4 map — `MikeBronner\CleanCode\Tests\` →
`tests/` — is what resolves a test-side class *by name*; a class-based test
declares the matching namespace (a file in `tests/Ruleset/` declares
`namespace MikeBronner\CleanCode\Tests\Ruleset`). It does not gate collection:
PHPUnit loads the file by path, so a mismatched namespace still runs, just under
the wrong class name. Pest's functional tests declare no namespace at all.

### Which suite a test goes in

`tests/Standards/`, `tests/Ruleset/` and `tests/Rules/` all hold per-rule tests.
They divide by what the test is a verdict about:

| Suite | The question it answers | Put new tests here? |
|---|---|---|
| `tests/Standards/` | Does this **sniff** behave correctly? One file per custom CleanCode sniff, driven by `analyzeFixture()` against `tests/fixtures/<Name>Sniff/`. | Yes — every new custom sniff. |
| `tests/Ruleset/` | Does **`rules.xml`** wire and configure this standard correctly? Registration, the configured `<properties>`, the `<exclude>`s, and the several sniffs of one standard together. | Yes — every standard carried by third-party sniffs. |
| `tests/Rules/` | The same question as `tests/Ruleset/`, under an older name. | No — `tests/Ruleset/` is the canonical home. |

`tests/Rules/` holds exactly four files — `AvoidConditionalsRulesTest`,
`ExceptionsRulesTest`, `LineLengthRulesTest`, `OperatorSpacingRulesTest`. They
stay where they are: they pass, and moving them buys nothing but a diff.

Open a wiring test with a registration assertion — `buildRuleset()`, then
`expect($ruleset->sniffCodes)->toHaveKey(…)`, as
`tests/Ruleset/UnusedUsesTest.php` does. That assertion is what makes a dropped
or misspelled `<rule ref>` fail the build instead of quietly disabling the
standard. Three of `tests/Rules/`'s four already carry it; the directory is
legacy in name only, not in rigour.

Four files reach that verdict **indirectly** instead, asserting violations that
can only appear if the sniff is wired into `rules.xml` — whether by running the
master ruleset and scoping the assertions, narrowing the built ruleset, or
filtering the phpcs run with `--sniffs`:
`tests/Rules/LineLengthRulesTest.php`,
`tests/Ruleset/CasingConventionsRulesetTest.php`,
`tests/Ruleset/NoDeadCodeRulesetTest.php` and
`tests/Ruleset/UnusedLocalVariableTest.php`. A sniff dropped from `rules.xml`
falls out of the report and their violation assertions fail, so the wiring is
still pinned. Prefer the explicit `sniffCodes` key check anyway: it names the
missing rule instead of reporting an absent violation.

**One `tests/Standards/` file is the norm even when `rules.xml` configures the
sniff.** Exactly two custom sniffs carry a live `<properties>` block —
`CleanCode.CodeSize.TooManyMethods` and `CleanCode.Classes.ExcessiveClassLength`
— and only one of them is split. `buildRuleset()` hands back the ruleset
`rules.xml` actually parsed, so a behaviour test can read the configured
instance and pin the shipped values where it stands:
`tests/Standards/TooManyMethodsTest.php` asserts `maxmethods` is 25 and
`ignorepattern` is the shipped regex, in the same file as the behaviour. Do not
add a second file just because a `<properties>` block exists.

`ExcessiveClassLength` is split for a specific reason: its behaviour test lowers
`minimum` through `$configure` so the fixtures need not be three thousand-line
classes, which leaves it unable to also stand as the record of the shipped
threshold of 1000. `tests/Ruleset/ExcessiveClassLengthTest.php` carries that
half, and each docblock names what it owns. **Split a sniff only when its
behaviour test must override the shipped configuration to do its own job.**

Four files sit outside the table and stay where they are.
`tests/Standards/NoInlineIfStatementsTest.php` covers a third-party sniff, while
`tests/Ruleset/DisallowStaticMembersTest.php`,
`tests/Ruleset/MultilineStringsTest.php` and
`tests/Ruleset/NoDeadCodeRulesetTest.php` cover custom ones.
`NoDeadCodeRulesetTest` is the only test of any kind for
`CleanCode.DeadCode.UnusedPrivateElements`: it drives that sniff alongside the
three third-party sniffs the No Dead Code standard also needs, which is why it
sits in `tests/Ruleset/` and not beside the other custom-sniff tests. Follow the
table rather than these four.

#### Layouts this table supersedes

Four PRs each invented a layout for testing a rule wired into `rules.xml`,
before this table existed. This section records what survived of each, so a
reader of those PRs does not copy a dead pattern:

| Introduced by | The layout | Where it stands |
|---|---|---|
| PR #200 | `tests/Standards/<Name>Test.php`, namespace `MikeBronner\Tests\Standards`, its own `autoload-dev` mapping, a flat `.inc`/`.inc.fixed` fixture pair | **Directory kept, everything else dropped.** `tests/Standards/` is canonical for a custom sniff's behaviour; the `MikeBronner\Tests\` namespace and its mapping are gone, and fixtures moved to `tests/fixtures/<Name>Sniff/*.php`. |
| PR #209 | `CleanCode/Tests/<Category>/<Name>Test.php`, namespace `MikeBronner\CleanCode\Tests\<Category>`, discovered by a `CleanCode/Tests` testsuite | **Dropped whole.** No testsuite names `CleanCode/Tests/` any more; only the two `AbstractSniffUnitTest` files above still sit there, uncollected. |
| PR #196 | `tests/Ruleset/<Name>RulesetTest.php` with `tests/Ruleset/Fixtures/<subject>/` split `compliant.inc`/`violations.inc`/`violations.inc.fixed` | **Directory kept, fixtures dropped.** `tests/Ruleset/` is canonical for wiring tests; no `Fixtures/` directory survives, and the split-fixture idea became the repo-wide `passing.php`/`failing.php`/`autofixed.php` contract. |
| PR #212 | `tests/Rules/` with `tests/Rules/Fixtures/*.inc`, and the `$ruleset->sniffCodes` registration assertion | **Closed to new work; its assertion kept.** `tests/Rules/` is `tests/Ruleset/` under an older name, but the registration assertion it introduced is now the standard opening of every wiring test. |

What survived is two directories — PR #200's `tests/Standards/` and PR #196's
`tests/Ruleset/` — and one assertion, PR #212's `sniffCodes` registration check.
Every namespace, wiring and fixture layout those PRs introduced was replaced by
the `tests/fixtures/` contract below.

`CONTRIBUTING.md` is the single source of truth for all of this. Where an
issue's acceptance criteria carry an older fixture or harness convention —
several still quote a `Fixtures/<SniffClassName>/` layout that exists nowhere in
this repo — this file wins.

### The fixture contract

Every sniff owns a directory under `tests/fixtures/` named for its **class short
name** — `CleanCode.Arrays.ArrayAccessors` → `ArrayAccessorsSniff/`. Inside it,
up to three fixtures carry fixed names:

| Fixture | Meaning | Required? |
|---|---|---|
| `passing.php` | code the sniff must leave alone | **always** |
| `failing.php` | code the sniff must flag | **always** |
| `autofixed.php` | `phpcbf`'s output for `failing.php` | fixable sniffs only |

Fixtures under `tests/fixtures/` are all `.php`. `passing.php` and `failing.php`
are the floor — every sniff carries both. Only `autofixed.php` is optional, and
only because a detection-only sniff has no safe mechanical rewrite to assert.

One directory sits outside this contract: `tests/Ruleset/fixtures/` holds
`clean.inc`, `violations.inc`, `edge-cases.inc` and `fixable.inc`/`.inc.fixed`
for `NoDeadCodeRulesetTest`, which drives the `phpcs`/`phpcbf` binaries directly
rather than going through `tests/Helpers.php`. The `.inc` extension is
load-bearing there: the PSR-12 self-lint scans `.php` only, so `.inc` keeps the
deliberately violating fixtures out of `composer lint`, and the phpcs invocation
opts back in with `--extensions=inc`. New fixtures do not go there — use
`tests/fixtures/` and the contract above.

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

A sniff that reads the file's **path** puts those extra fixtures in a
subdirectory spelling the path out, because the three fixed names also fix the
directory the fixture sits in, and that directory is not the one the sniff needs
to see. `ApiControllerNamespaceSniff/app/Http/Controllers/API/` is the example:
`passing.php` and `failing.php` can only ever exercise the namespace half of
that rule, so the path half lives one level down. Subdirectories are invisible
to the contract sweep, which looks only for the three fixed names.

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
- `analyzeFixtureWithRulesetProperties($sniffCode, $fixture, $properties)` — the
  same, but setting the properties the way a *consuming ruleset* does: string
  values through `Ruleset::setSniffProperty()`, exactly as parsing a
  `<property>` element does. Not interchangeable with `$configure` above, which
  assigns to the property directly and so always hands over a correctly typed
  value: only the XML path trims the value and turns an empty string into
  `null`, which is what decides whether an empty `<property>` element
  configures the sniff or aborts the ruleset parse with a `TypeError`.
- `analyzeRulesetFixture([$sniffCodes], $directory, $fixture)` — the
  `_rulesets/` equivalent, for a standard carried by several sniffs.
- `analyzeWithMasterRuleset($path)` — the *whole* ruleset, every sniff active.
  Use it when the point is how rules interact; scope the assertions to the
  sources under test so unrelated additions to `rules.xml` cannot break them.
- `analyzeWithStandard($standard, $path)` — a whole *vendor* standard by name,
  outside `rules.xml`. Narrow: `rules.xml` references vendor sniffs one at a
  time, so the master ruleset can only report on what is already wired in. Use
  this when the question is "does anything in this vendor standard already
  cover the case?" — the one a new custom sniff has to settle, and the one a
  vendor upgrade can quietly change the answer to.
- `buildRuleset()` — `[$config, $ruleset]`, for asserting a sniff is registered.
- `installedPhpcsViolations($standard, $path, $sniffCode)` — the only helper
  that leaves this process: it runs the installed `vendor/bin/phpcs` binary,
  from a working directory outside the package, and returns what one sniff
  reported. Use it for a smoke test that the *shipped* package works, and
  nothing else — every in-process helper here supplies the standards
  registration itself, so none of them can tell a registered package from an
  unregistered one.
- `violationSourcesByLine()`, `violationCountsByLine()`, `violationTuples()`,
  `allViolationSourcesByLine()`, `violationFixableFlags()` — collapse PHPCS's
  nested `line => column => violations` structure into something assertable.
- `autofixedContents($file)` — run the fixer and return the result.

Every helper that narrows to particular sniffs builds the master ruleset and
*then* narrows `$ruleset->sniffs`, rather than restricting PHPCS via
`$config->sniffs`. This is load-bearing: under
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
3. **Add it to the contract sweep** — one entry in `SWEPT_SNIFFS` if it reports
   errors, or in `SWEPT_WARNING_SNIFFS` if its violations are warnings. Both
   lists live in `tests/Sniffs.php`, loaded by `tests/bootstrap.php` rather than
   declared in a suite file, because more than one suite's datasets read them
   and PHPUnit resolves every data provider before it has loaded every test
   file. (The failing-fixture assertion reads the matching violation list; both
   lists feed the passing and registration datasets, so the floor cannot be
   half-applied.) Add it to
   `autofixable sniffs` too, and, if its fixer is total,
   `sniffs whose fixer resolves every violation`. That alone gives it the
   generic passing/failing/autofix/idempotence coverage.

   A sniff **scoped by path** is the one exception: the sweep processes each
   fixture where it lives, under `tests/`, and the scoping is decided from the
   file's path alone, so the failing fixture would report nothing. That covers
   a sniff scoped by `rules.xml` with an `<include-pattern>`/`<exclude-pattern>`
   and one that scopes itself from its own property — the sweep configures
   nothing, so a property-scoped sniff cannot even be pointed at its own
   fixtures there. Leave it out of the datasets, record why in a comment beside
   the enumeration in `tests/Sniffs.php`, and reach its fixtures from its own
   test file instead: `stageFixtureOutsideTests()` when the path only
   has to *match* a rule, or a small committed project under the fixture
   directory when the rule's answer depends on other files really being there.
   `CleanCode.Testing.RequireTestFile` is the second shape — it resolves a
   companion test, so `tests/fixtures/RequireTestFileSniff/` holds `src/`,
   `app/` and `tests/` trees whose contents are the thing under test.
4. **Add its behaviour test** at `tests/Standards/<Name>Test.php`, asserting the
   exact lines, columns, and violation sources — see
   `tests/Standards/NotOperatorSpacingTest.php` for the simple shape and
   `tests/Standards/ArrayAccessorsTest.php` for a thoroughly documented one.
   Sitting in `tests/Standards/` with a `Test.php` suffix is what collects it —
   see "How a test gets collected"; there is nothing else to register.

   **One file is normally the whole answer, even when `rules.xml` configures
   the sniff.** Read the configured instance off `buildRuleset()` and pin the
   shipped `<properties>` right here, as `tests/Standards/TooManyMethodsTest.php`
   does. Add a second file at `tests/Ruleset/<Name>Test.php` only when this test
   has to *override* the shipped configuration to do its own job — see "Which
   suite a test goes in" and the `ExcessiveClassLength` pair.
5. **Wire third-party rules into `rules.xml`** when a standard is enforced by an
   existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`. Custom
   CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line. Pin the registration and the
   configured behaviour with a test in `tests/Ruleset/` — see
   `tests/Ruleset/UnusedUsesTest.php` for the single-sniff shape and
   `tests/Ruleset/TypeHintsRulesetTest.php` for a standard carried by several
   sniffs at once.

   A third-party sniff's `<properties>` are pinned **through behaviour**, not by
   reading the instance: no vendor sniff's property names are asserted anywhere
   here, because they belong to the vendor and can be renamed on upgrade. Write
   a fixture that only passes under the configured value —
   `UnusedUsesTest`'s `docblock-only.php` is silent only because
   `searchAnnotations="true"`, and `tests/Rules/LineLengthRulesTest.php` probes
   lines of exactly 100, 101, 120 and 121 characters to pin `lineLimit` at 100
   and `absoluteLineLimit` at 120.

   A sniff from a **new Composer package** needs that package's path added to
   the `installed_paths` list in `restoreInstalledPaths()` (`tests/Helpers.php`)
   as well.
   Composer writes the path into `CodeSniffer.conf` on install, but every test
   builds its `Config` through `ConfigDouble`, which blanks that file — so a
   package missing from the list does not fail on its own sniff, it makes the
   whole `rules.xml` parse fail and takes the entire suite down with it.

   Where a third-party sniff emits more codes than the standard being adopted,
   `<exclude>` the extra ones and pin **both halves**: that they stay silent
   through `rules.xml`, and that they still fire without the excludes.
   Otherwise a fixture that trips nothing looks exactly like a working exclude
   list. `tests/Ruleset/UndefinedVariableTest.php` is the template.
6. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there. A rule that replicates a **PHPMD**
   rule rather than a mikebronner.dev clean-code standard is documented under
   `docs/phpmd/<ruleset>-<rulename>.md` instead, and linked from the README's
   "PHPMD rule coverage" list — the mapping (PHPMD rule → PHPCS sniff) is what
   the doc has to state, so `phpmd` no longer needs to run for that rule.

## Writing the tests

Use Pest's functional style — `it('does the thing', function () { … })` — not a
`TestCase` subclass. Where several fixtures exercise the same assertion, use a
dataset (`->with([...])`) rather than copy-pasting the test.
`tests/Ruleset/NoDeadCodeRulesetTest.php` is the only surviving subclass; it
shells out to the `phpcs`/`phpcbf` binaries and predates the Pest migration.
Do not copy its shape.

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
