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

One file still extends that harness and is the only exception:
`CleanCode/Tests/Operators/DisallowNewlineAroundEvaluativeOperatorsUnitTest.php`,
with its `.inc` and `.inc.fixed` fixtures beside it. No `<testsuite>` in
`phpunit.xml.dist` covers `CleanCode/Tests/`, so `composer test` never collects
it — it is dead weight awaiting deletion, not a second convention. The sniff it
names still has live assertions under `tests/`
(`tests/Integration/OperatorRulesIntegrationTest.php`), so deleting it loses no
coverage. Add nothing to `CleanCode/Tests/`.

The `DeadCode` half of that pair is gone: #206 deleted
`CleanCode/Tests/DeadCode/UnusedPrivateElementsUnitTest.php` and its `.inc`
after porting their coverage to `tests/Standards/UnusedPrivateElementsTest.php`
and `tests/fixtures/UnusedPrivateElementsSniff/`.

## Layout

```
rules.xml                                  # master ruleset — standards get wired in here
CleanCode/
├── ruleset.xml                            # the installable CleanCode standard
├── Helpers/<Name>.php                     # token-stream decisions shared by several sniffs
├── Sniffs/
│   └── <Category>/<Name>Sniff.php         # one sniff per file
└── Tests/                                 # one legacy AbstractSniffUnitTest file — uncollected, do not extend
docs/standards/                            # one doc per clean-code standard
docs/phpmd/                                # one doc per replicated PHPMD rule
tests/
├── bootstrap.php                          # PHPCS test constants + ConfigDouble autoloading; loads Sniffs.php
├── Sniffs.php                             # the sniff enumerations more than one suite reads
├── Pest.php                               # Pest config; requires Helpers.php
├── Helpers.php                            # the sniff-driving helper functions
├── fixtures/
│   ├── <Name>Sniff/                       # per-sniff fixtures, named for the sniff class
│   ├── <Name>/                            # per-helper fixtures, named for the helper class
│   └── _rulesets/<Standard>/              # fixtures for standards carried by several sniffs
├── Contract/                              # the generic three-fixture sweep
├── Helpers/                               # the shared classes under CleanCode/Helpers/, plus the staging teardown
├── Standards/                             # a custom sniff's own behaviour — one file per sniff
├── Rules/                                 # four older files doing tests/Ruleset/'s job — closed to new work
├── Ruleset/                               # a standard as rules.xml wires and configures it, plus its own fixtures/
└── Integration/                           # whole-ruleset behaviour, with its own fixtures/
```

### How a test gets collected

`composer test` runs Pest against `phpunit.xml.dist`, which declares one
`<testsuite>` per suite directory — `Contract`, `Helpers`, `Integration`,
`Rules`, `Ruleset`, `Standards` — each pointing at `tests/<Name>`. A
`<directory>` defaults to `suffix="Test.php"`, so a file is collected when it
sits in one of those six directories **and** its name ends `Test.php`. Nothing
else registers
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
`NoDeadCodeRulesetTest` drives `CleanCode.DeadCode.UnusedPrivateElements`
alongside the three third-party sniffs the No Dead Code standard also needs,
which is why it sits in `tests/Ruleset/` and not beside the other custom-sniff
tests. Since #206 it is no longer that sniff's only test: the sniff's own
behaviour moved to `tests/Standards/UnusedPrivateElementsTest.php`, where the
table puts it, and this file kept the wiring half. Follow the table rather than
these four.

#### Layouts this table supersedes

Before this table existed, several PRs each settled on their own way to test a
rule wired into `rules.xml`. This section records what survived of each, so a
reader of those PRs does not copy a dead pattern. Rows are in the order they
landed on `main`, which is what "first" means throughout — several of these
branches were written in a different order than they merged:

| Landed | Introduced by | The layout | Where it stands |
|---|---|---|---|
| 2026-07-17 | PR #212 | `tests/Rules/<Name>RulesTest.php` with flat `tests/Rules/Fixtures/<Name>.inc`/`.inc.fixed`. Also the first `autoload-dev` mapping (`MikeBronner\CleanCode\Tests\` → `CleanCode/Tests/` and `tests/`) and the `$ruleset->sniffCodes` registration assertion. | **Directory closed to new work; the assertion kept.** `tests/Rules/` answers `tests/Ruleset/`'s question under the older name. The registration assertion is now the standard opening of every wiring test, and the mapping survives in narrowed form (`MikeBronner\CleanCode\Tests\` → `tests/`). |
| 2026-07-17 | PR #196 | `tests/Ruleset/<Name>RulesetTest.php` with `tests/Ruleset/Fixtures/<subject>/` split `compliant.inc`/`violations.inc`/`violations.inc.fixed` | **Directory kept, fixtures dropped.** `tests/Ruleset/` is canonical for wiring tests; no capital-F `Fixtures/` directory survives, and the split-fixture idea became the repo-wide `passing.php`/`failing.php`/`autofixed.php` contract. |
| 2026-07-17 | PR #200 | `tests/Standards/<Name>Test.php`, namespace `MikeBronner\CleanCode\Tests\Standards`, flat `tests/Standards/Fixtures/<Name>.inc`/`.inc.fixed`. It added no wiring of its own — `composer.json` is untouched by its merge. | **Directory kept, fixtures dropped.** `tests/Standards/` is canonical for a custom sniff's behaviour; its fixtures moved to `tests/fixtures/<Name>Sniff/*.php`. |
| 2026-07-20 | PR #209 | **No new layout.** It reused PR #196's — `tests/Ruleset/RequireConstructorPropertyPromotionRulesetTest.php` plus split fixtures under `tests/Ruleset/Fixtures/RequireConstructorPropertyPromotion/`. | **As PR #196 above.** The test still stands, under the same name; only its fixtures moved. |
| 2026-07-20 | PR #214 | **No new layout.** It reused PR #196's too — `tests/Ruleset/UnusedUsesTest.php` plus split fixtures under `tests/Ruleset/Fixtures/UnusedUses/`. | **As PR #196 above.** |

What survived is two directories for new work — PR #196's `tests/Ruleset/` and
PR #200's `tests/Standards/` — and one assertion, PR #212's `sniffCodes`
registration check. Every fixture layout those PRs introduced was replaced by
the `tests/fixtures/` contract below.

**Read these PRs by their merge commits, not their branches.** Two of them carry
a layout on the branch that was restructured away before the merge commit: PR
#209 had a `CleanCode/Tests/Constructors/` variant, and PR #214 had a
`MikeBronner\Tests\` namespace with a second `autoload-dev` mapping to match.
Neither is in the tree the merge landed, and neither is a pattern to copy.

`CONTRIBUTING.md` is the single source of truth for all of this. Where an
issue's acceptance criteria carry an older fixture or harness convention —
several still quote a `Fixtures/<SniffClassName>/` layout that exists nowhere in
this repo — this file wins.

### The shared helpers

`CleanCode/Helpers/` holds the decisions more than one sniff has to make about
the token stream. `FunctionCalls::isGlobalFunctionCall()` is the first:
"is this `T_STRING` a call to PHP's own global function, or a same-named method,
declaration, class, attribute, or imported symbol?".

**Route through it rather than hand-rolling the test again.** Every sniff that
flags a global function call needs that answer, and before the helper existed
each one carried its own copy: the copies drifted, and each new sniff inherited
whichever gaps its nearest neighbour had. One implementation means a shape fixed
once is fixed everywhere.

`tests/Helpers/` and `tests/Helpers.php` are different things, and the names are
the only thing they share: the directory is a suite covering the shared classes
under `CleanCode/Helpers/`, the file holds the Pest helper functions every suite
here calls. One test in the directory covers the file instead —
`RemoveStagedDirectoryTest.php`, over the staging teardown that runs after every
test (#380). It is the only one.

A helper carries its own fixtures under `tests/fixtures/<Name>/` and its own
tests under `tests/Helpers/<Name>Test.php`, driven by `parseFixture()` — it
tokenises a fixture without running a sniff, which is what lets a test read the
helper's verdict for every shape rather than only the ones some sniff's own name
list would let through. A helper directory holds no sniff, so the contract sweep
in `tests/Contract/` never reaches it: `tests/fixtures/<Name>/` is bound to its
suite only by the helper's own test file.

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

The one exception to "all fixtures are `.php`" is a sniff that reads Blade
views (`CleanCode.Livewire.ComponentMarkup`). Its three contract fixtures stay
`.php`, because `LocalFile` tokenises both extensions identically and the
contract sweep resolves them by fixed name; two extra `.blade.php` fixtures sit
beside them, for the two things only the real file type can assert:

- `component.blade.php` — the extension registered in `rules.xml` reaches the
  sniff, tested through the whole master ruleset rather than assumed.
- `no-php-code.blade.php` — a view with no `<?php` tag at all, which is what
  the `Internal.NoCodeFound` exclude-pattern in `rules.xml` exists for. Every
  `.php` fixture carries a trailing open tag instead, because that suppression
  deliberately does not cover `.php`.

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

- `parseFixture($directory, $fixture)` — tokenise a fixture without running any
  sniff, for testing the shared classes under `CleanCode/Helpers/` directly.
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
   no per-sniff registration in `CleanCode/ruleset.xml` is needed. If it flags a
   call to a global PHP function, call
   `FunctionCalls::isGlobalFunctionCall()` — see "The shared helpers" above —
   instead of writing that check again.

   **Give every token-kind classification array a named family.** A
   `private const` enumerating PHPCS token constants — the `EXPRESSION_SCOPES`,
   `CHAIN_OPERATORS`, `NON_FUNCTION_CALL_PRECEDERS` shape — must carry a doc
   comment naming the **canonical, independent source** of the family it
   classifies, and accounting for every member of that source: included, or
   excluded with a one-line reason, inline in that same comment. A canonical
   source is a `PHP_CodeSniffer\Util\Tokens::$…` grouping array, or a closed
   list stated with its provenance ("every `T_*_ARROW` constant `token_name()`
   reports"). A family read off the array's own current contents does not
   count — it is complete by construction and asks nothing of the author.
   Where the source and the family differ, say so and prove it, rather than
   quietly widening either: `T_FN` is in `EXPRESSION_SCOPES`'s family but not
   in `Tokens::$scopeOpeners`, and the test below asserts both halves of that
   against a tokenized arrow function.

   `MultiLineStatementIndentSniff::EXPRESSION_SCOPES` is the worked example,
   and `accounts for every scope opener PHPCS defines` in
   `tests/Standards/MultiLineStatementIndentTest.php` is the test shape that
   holds it: the family read out of PHPCS at run time, the accounting parsed
   out of the docblock, neither restated in the test. A PHPCS release that
   adds a scope opener reddens the suite instead of slipping past it.

   This is a checklist step rather than a check that runs over every such array
   in the tree, because **nothing declares which family a given array answers
   to.** Finding the declarations is easy — tokenize the tree, take every
   `private const` of `T_*` constants — but the family each one is measured
   against is a judgement (`CHAIN_OPERATORS` against dereference operators,
   `RAW_CONTENT` against raw-content tokens), and no registry maps an array to
   its family, so an automated checker has nothing to compare against. The
   lighter, purely structural alternative — assert every such constant has an
   adjacent family doc comment — was considered and **rejected**: it says
   nothing about whether the named family is canonical or the accounting
   complete, which is the whole loophole this convention closes, and switching
   it on would fail against every already-shipped classification array (some
   ninety of them, across forty-odd sniffs), a retrofit issue #316 scopes out.

   When a sniff does ship a classification array missing a member of its
   family, open the issue **against this checklist step**, not against the
   individual sniff. The step is what failed; fixing the one array again
   leaves the step exactly as unable to catch the next one.
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
   generic passing/failing/autofix/idempotence coverage. Every one of those
   lists is kept in alphabetical order, enforced by `it keeps every sniff list
   in alphabetical order` — file each entry in its sorted position.

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

   **Carry the shipped-install run over too.** The sweep is not only generic
   coverage: through `tests/Contract/ShippedPackageSmokeTest.php` it is the only
   place a sniff is executed by the real `vendor/bin/phpcs` against `rules.xml`.
   Every other test drives PHP_CodeSniffer in process through `ConfigDouble`,
   which supplies the registration Composer would have supplied — so a package
   that never registered itself with the installed standards passes all of them,
   `buildRuleset()`'s registration assertion included. Leaving a sniff out of the
   datasets drops that run, so add it back in its own test file with
   `installedSniffRun()`, asserting the staged in-scope path reports and both an
   out-of-scope path and `passing.php` stay silent at status 0. Every path-scoped
   sniff carries one; `tests/Standards/UnitTestExternalConcernsTest.php` is the
   property-scoped shape and `tests/Standards/NoProceduralCodeTest.php` the
   `rules.xml`-scoped one.
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
composer lint      # PSR-12 over the sniff and test code (fixtures excluded)
composer lint:self # the shipped ruleset against CleanCode/, at zero errors

vendor/bin/pest --testsuite=Standards      # one suite
vendor/bin/pest --filter='flags every'     # one test
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```

### The self-lint

`composer lint:self` runs the shipped ruleset over the package's own sniff
sources through `phpcs.self.xml`. **Zero errors is the bar**, CI runs it on every
pull request, and **it passes**: `phpcs --standard=phpcs.self.xml` reports 0
errors. It got there from 3103. Keep it there — a new error is a regression, not
a starting point for a discussion.

No *file* is granted an allowance. Nine rules are silenced ruleset-wide, and
roughly two dozen individual lines carry a `phpcs:ignore` with its reason on the
same line. Both kinds are readable at the point they apply.

**A silenced rule is not the same as a tuned exclusion list.** The bar moves to
the code, never the code to the bar, so a rule is only silenced when following it
would produce something wrong rather than something inconvenient. Three groups,
each argued at the exclusion in `phpcs.self.xml`:

- **Laravel helpers this package does not depend on.**
  `Arrays.ArrayAccessors` prescribes `data_get()`; `Arrays.ConvertToCollection`
  and `Collections.OnlyUseCollectionMethods` prescribe `collect()`. A fixer run
  would emit calls to functions that do not exist here.
- **Naming length, asked of the wrong subject.** `Naming.ShortVariable`,
  `Naming.LongVariable` and `Naming.LongClassName`: `$i` is the index idiom in a
  `for` walk, `$isStatementScopeOpener` earns its length, and a sniff's class
  name is fixed by the sniff code consumers write in their rulesets.
- **Complexity, asked of a token walker.** `Metrics.MethodNestingLevel`,
  `Metrics.CyclomaticComplexity`, `Metrics.NPathComplexity` and
  `Metrics.ExcessiveClassComplexity` accounted for 190 of the last 227 errors,
  and all four fire on the same thing. A sniff reads a flat token array and
  answers questions about nested structure: a loop with a switch inside it, a
  branch per token type, a guard per malformed-source case. The branching is
  what the work *is*. Splitting a walk into more methods moves the branches
  without removing them and costs the reader the one place the walk can be seen.

All nine stay at full severity in `rules.xml`, so a consumer's application code
is still held to every one of them. Application code branches because somebody
made a decision; a token walker branches because the grammar does.

The step runs **last** in the workflow, after the lint and the test suite, so a
contributor sees whether their own change is sound before anything else fails.

Scope is set by `<file>CleanCode</file>` rather than by an exclude-pattern.
PHPCS applies an exclude-pattern even to a path named on the command line, so
excluding `tests/` would break any run that lints a fixture by explicit path —
which several suites here do. Naming `CleanCode/` leaves everything else out of
scope with nothing suppressed. The test tree still gets PSR-12 via
`composer lint`.

Only errors are counted, because only errors gate `phpcs`. Warnings stand at
3386, and `CleanCode.Conditionals.AvoidConditionals` alone accounts for most of
them — admitting warnings would be a far larger decision than this gate.

### `process()` and the untyped `$stackPtr`

Settled convention, established by PR #168 — not a per-sniff judgement call.
`Sniff::process(File $phpcsFile, $stackPtr)` leaves `$stackPtr` untyped because
narrowing an inherited parameter to `int` breaks contravariance with the
PHP_CodeSniffer `Sniff` interface, which is a fatal error. Silence
`SlevomatCodingStandard.TypeHints.ParameterTypeHint` at the signature and say
why, as `CleanCode/Sniffs/Controllers/ManualModelResolutionSniff.php` does:

```php
// phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint -- interface-mandated, see CONTRIBUTING.md
public function process(File $phpcsFile, $stackPtr): void
```

Name the **sniff**, not the message code. Without a `@param` annotation the
sniff reports `MissingAnyTypeHint` rather than `MissingNativeTypeHint`, so a
code-specific suppression stops matching the moment the docblock goes.

### Comments

A comment explains **unexpected behavior** or an **unexpected requirement**.
Nothing else. There are no PHPDoc blocks in this package.

Keep a comment when a reader would otherwise be surprised:

- "PHPCS applies an `<exclude-pattern>` even to a path named on the command
  line."
- "Left unset, this property follows whichever interpreter runs `phpcs`, so a
  consumer on a newer PHP gets findings their CI did not."

Cut anything that restates what the code or config line already says, narrates
how a decision was reached, or repeats a `docs/standards/*.md` the line already
points at. An XML comment and a docblock cannot be tested, so they drift in
silence — `rules.xml` has shipped three false claims that way.

Never make a comment a test's expected value. An enumeration a test needs is
data, so it belongs in code: see `MultiLineStatementIndentSniff`'s
`EXPRESSION_SCOPES` / `NON_EXPRESSION_SCOPES` pair, whose union the suite
checks against PHPCS's own register.

An XML comment cannot contain `--`, so a CLI flag written inside one makes the
ruleset unparseable. PHPCS then reports `Comment must not contain '--'` and
exits 3, and a suite that loads the standard hangs rather than failing. Write
"the config-set command", not the flag.
