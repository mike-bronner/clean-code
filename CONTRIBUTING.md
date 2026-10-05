# Contributing

This package is a PHP_CodeSniffer standard (`composer.json` type
`phpcodesniffer-standard`). It ships **exactly one** installed standard, and a
consumer who installs the package gets it in `vendor/bin/phpcs -i`:

**`CleanCode`**, in `CleanCode/ruleset.xml`. That one file is the whole rule
set: the custom sniffs in `CleanCode/Sniffs/`, the PSR and Slevomat wiring, the
`php_version` pin, the Blade `extensions` argument and the
`Internal.NoCodeFound` suppression. A consumer writes `<rule ref="CleanCode"/>`
and gets all of it.

Two mechanics make that one file work, and both are worth knowing before you
edit it:

- A standard's *name* is the name of the directory holding its `ruleset.xml`,
  never the `name=` attribute inside. So `CleanCode/` is this package's public
  API. It is also the prefix on every code the custom sniffs report, which is
  why the directory is not free to be renamed: `CleanCode.Naming.ShortVariable`
  in a report, `CleanCode` in a consumer's config, and
  `MikeBronner\CleanCode\Sniffs\…` in the source are the same word in three
  places.
- A ruleset's sibling `Sniffs/` directory is registered automatically when the
  ruleset loads. Nothing in `CleanCode/ruleset.xml` refers to the custom sniffs,
  and nothing needs to.

Adding a second directory with a `ruleset.xml` in it would install a second
standard. `tests/Contract/InstalledStandardTest.php` fails if one appears.

**On "the master ruleset",** which this document and the test names both
use. It means `CleanCode/ruleset.xml`: the one ruleset that
wires every rule the package ships, as opposed to an individual sniff or a
consumer's own ruleset. There is no second file for it to be master *of* any
more, and the phrase is kept because the test names already use it.

Tests are written with **[Pest](https://pestphp.com/)** and live under `tests/`.
New tests never use PHP_CodeSniffer's own `AbstractSniffTestCase` harness (named
`AbstractSniffUnitTest` before PHP_CodeSniffer 4): it
derives the fixture path from the test class itself — `<testsDir>/<Category>/
<Name>UnitTest.*`, one flat set of siblings — and forces the fixer expectation
to `<input>.fixed`. Neither can express the fixture layout below, which gives a
sniff its own directory and fixed names inside it. Tests here drive the real
`phpcs`/`phpcbf` through the helpers in `tests/Helpers.php` instead.

One file still extends that harness, under its PHP_CodeSniffer 3 name, and is the
only exception:
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
CleanCode/
├── ruleset.xml                            # the CleanCode standard, entire — rules get wired in here
├── Helpers/<Name>.php                     # decisions shared by several sniffs, about the tokens or the path
├── Sniffs/
│   └── <Category>/<Name>Sniff.php         # one sniff per file
└── Tests/                                 # one legacy AbstractSniffUnitTest file — uncollected, do not extend
resources/boost/guidelines/                # one Boost guideline per standard, and per PHPMD rule no standard states
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
├── Ruleset/                               # a standard as CleanCode/ruleset.xml wires and configures it, plus its own fixtures/
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
| `tests/Ruleset/` | Does **`CleanCode/ruleset.xml`** wire and configure this standard correctly? Registration, the configured `<properties>`, the `<exclude>`s, and the several sniffs of one standard together. | Yes — every standard carried by third-party sniffs. |
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
can only appear if the sniff is wired into `CleanCode/ruleset.xml` — whether by running the
master ruleset and scoping the assertions, narrowing the built ruleset, or
filtering the phpcs run with `--sniffs`:
`tests/Rules/LineLengthRulesTest.php`,
`tests/Ruleset/CasingConventionsRulesetTest.php`,
`tests/Ruleset/NoDeadCodeRulesetTest.php` and
`tests/Ruleset/UnusedLocalVariableTest.php`. A sniff dropped from `CleanCode/ruleset.xml`
falls out of the report and their violation assertions fail, so the wiring is
still pinned. Prefer the explicit `sniffCodes` key check anyway: it names the
missing rule instead of reporting an absent violation.

**One `tests/Standards/` file is the norm even when `CleanCode/ruleset.xml` configures the
sniff.** Exactly two custom sniffs carry a live `<properties>` block —
`CleanCode.CodeSize.TooManyMethods` and `CleanCode.Classes.ExcessiveClassLength`
— and only one of them is split. `buildRuleset()` hands back the ruleset
`CleanCode/ruleset.xml` actually parsed, so a behaviour test can read the configured
instance and pin the shipped values where it stands:
`tests/Standards/TooManyMethodsTest.php` asserts `maxmethods` is 25 and
`ignorepattern` is the shipped regex, in the same file as the behaviour. Do not
add a second file just because a `<properties>` block exists.

`ExcessiveClassLength` is split for a specific reason: its behaviour test lowers
`minimum` through `$configure` so the fixtures need not be three thousand-line
classes, which leaves it unable to also stand as the record of the shipped
threshold of 1000. `tests/Ruleset/ExcessiveClassLengthTest.php` carries that
half. **Split a sniff only when its
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
rule wired into `CleanCode/ruleset.xml`. This section records what survived of each, so a
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

`CleanCode/Helpers/` holds the decisions more than one sniff has to make. Most
are about the token stream, and one is about the file's path.
`FunctionCalls::isGlobalFunctionCall()` is a token-stream helper:
"is this `T_STRING` a call to PHP's own global function, or a same-named method,
declaration, class, attribute, or imported symbol?".

**Route through it rather than hand-rolling the test again.** Every sniff that
flags a global function call needs that answer, and before the helper existed
each one carried its own copy: the copies drifted, and each new sniff inherited
whichever gaps its nearest neighbour had. One implementation means a shape fixed
once is fixed everywhere.

One helper answers a question about the file's path instead:
`PathPatterns::matchesAny()`, "does this path match any of these `fnmatch`
patterns?". Any sniff that matches a file path against `fnmatch` patterns routes
through it, not only a sniff scoped to test files. That covers test-file,
feature-test, integration-test and route-file patterns, and exclude lists too.
The sniff keeps its pattern property and its defaults, and passes that property
to the helper as it is. The helper reads every backslash, in the path and in
each pattern, as a forward slash, so the sniff normalises neither. Before the
helper, seven sniffs each carried their own copy of the pattern loop, and the
copies drifted: they did not all normalise the path in the same place. It reads
strings, so it has no fixtures, and `tests/Helpers/PathPatternsTest.php` tests
it directly.

Two helpers answer what PHP_CodeSniffer 4 changed. `Declarations::name()` gives a
declaration's name, and `null` for a closure, an anonymous class, or a
declaration with no name yet. Call it instead of `File::getDeclarationName()`,
which throws for the first two. `NameTokens` holds the three tokens a qualified
name now arrives in, `T_NAME_QUALIFIED`, `T_NAME_FULLY_QUALIFIED` and
`T_NAME_RELATIVE`, and reads the last segment of one. A sniff that reads a
class or function name accepts those tokens beside `T_STRING`.

`AttributeCasts::isReturnedBy()` answers "does this method return Eloquent's
`Illuminate\Database\Eloquent\Casts\Attribute`?". It resolves the return type
through the file's `use` imports, aliases and group imports included, so a
different class named `Attribute` does not count.
`CleanCode.Models.ModelMagicMethodLocation` asks it to find a modern accessor in
a class body, and `CleanCode.Models.DisallowChainedPropertyFetch` asks it to
find the accessor a chain may live in.

`tests/Helpers/` and `tests/Helpers.php` are different things, and the names are
the only thing they share: the directory is a suite covering the shared classes
under `CleanCode/Helpers/`, the file holds the Pest helper functions every suite
here calls. One test in the directory covers the file instead —
`RemoveStagedDirectoryTest.php`, over the staging teardown that runs after every
test (#380). It is the only one.

Every helper has its own tests under `tests/Helpers/<Name>Test.php`. A helper
that has fixtures keeps them under `tests/fixtures/<Name>/`, and its tests drive
them through `parseFixture()`. Not every helper needs fixtures: `PathPatterns`
reads only strings, so its test calls it directly. `parseFixture()`
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
for `NoDeadCodeRulesetTest`, which drives the `phpcs`/`phpcbf` binaries in a
subprocess rather than analyzing in-process. The `.inc` extension is
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

- `component.blade.php` — the extension registered in `CleanCode/ruleset.xml` reaches the
  sniff, tested through the whole master ruleset rather than assumed.
- `no-php-code.blade.php` — a view with no `<?php` tag at all, which is what
  the `Internal.NoCodeFound` exclude-pattern in `CleanCode/ruleset.xml` exists for. Every
  `.php` fixture carries a trailing open tag instead, because that suppression
  deliberately does not cover `.php`.

A sniff that reads an ancestor through reflection needs that ancestor to
load the way a consumer's vendor parent loads, through Composer. A fixture
class never autoloads, so the sniff would take its unresolvable-ancestor path
instead of the one under test. Such ancestors live in
`tests/fixtures/_ancestors/`, which `composer.json` maps under `autoload-dev`
as `MikeBronner\CleanCode\Tests\Ancestors\`. The fixtures of
`CleanCode.TypeHints.PropertyTypeHint` and
`CleanCode.Classes.DisallowStaticMembers` extend them. A real vendor class still
serves where one has the shape a test needs, as `inherited-static.php` does with
`PHP_CodeSniffer\Util\Common`.

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
  sources under test so unrelated additions to `CleanCode/ruleset.xml` cannot break them.
- `analyzeWithStandard($standard, $path)` — a whole *vendor* standard by name,
  outside `CleanCode/ruleset.xml`. Narrow: `CleanCode/ruleset.xml` references vendor sniffs one at a
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
parsing `CleanCode/ruleset.xml` altogether — which is what pulls the custom CleanCode sniffs
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

   **Account for every token-kind classification array in code.** A
   `private const` enumerating PHPCS token constants — the `EXPRESSION_SCOPES`,
   `CHAIN_OPERATORS`, `NON_FUNCTION_CALL_PRECEDERS` shape — classifies a
   **canonical, independent family**: a `PHP_CodeSniffer\Util\Tokens::$…`
   grouping array, or a closed list with a stated provenance. Put the members
   the array leaves out in a sibling constant, and add a test that reads both
   constants and compares their union with the family. A family read off the
   array's own current contents does not count, because it is complete by
   construction.

   `MultiLineStatementIndentSniff`'s `EXPRESSION_SCOPES` /
   `NON_EXPRESSION_SCOPES` pair is the worked example, and
   `accounts for every scope opener PHPCS defines` in
   `tests/Standards/MultiLineStatementIndentTest.php` is the test shape that
   holds it: the family read out of PHPCS at run time, the accounting read out
   of the two constants, neither restated in the test. A PHPCS release that
   adds a scope opener reddens the suite instead of slipping past it.

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
   a sniff scoped by `CleanCode/ruleset.xml` with an `<include-pattern>`/`<exclude-pattern>`
   and one that scopes itself from its own property — the sweep configures
   nothing, so a property-scoped sniff cannot even be pointed at its own
   fixtures there. Leave it out of the datasets, record why in the issue that adds
   the sniff, and reach its fixtures from its own
   test file instead: `stageFixtureOutsideTests()` when the path only
   has to *match* a rule, or a small committed project under the fixture
   directory when the rule's answer depends on other files really being there.
   `CleanCode.Testing.RequireTestFile` is the second shape — it resolves a
   companion test, so `tests/fixtures/RequireTestFileSniff/` holds `src/`,
   `app/` and `tests/` trees whose contents are the thing under test.

   **Carry the shipped-install run over too.** The sweep is not only generic
   coverage: through `tests/Contract/ShippedPackageSmokeTest.php` it is the only
   place a sniff is executed by the real `vendor/bin/phpcs` against `CleanCode/ruleset.xml`.
   Every other test drives PHP_CodeSniffer in process through `ConfigDouble`,
   which supplies the registration Composer would have supplied — so a package
   that never registered itself with the installed standards passes all of them,
   `buildRuleset()`'s registration assertion included. Leaving a sniff out of the
   datasets drops that run, so add it back in its own test file with
   `installedSniffRun()`, asserting the staged in-scope path reports and both an
   out-of-scope path and `passing.php` stay silent at status 0. Every path-scoped
   sniff carries one; `tests/Standards/UnitTestExternalConcernsTest.php` is the
   property-scoped shape and `tests/Standards/NoProceduralCodeTest.php` the
   `CleanCode/ruleset.xml`-scoped one.
4. **Add its behaviour test** at `tests/Standards/<Name>Test.php`, asserting the
   exact lines, columns, and violation sources — see
   `tests/Standards/NotOperatorSpacingTest.php` for the simple shape and
   `tests/Standards/ArrayAccessorsTest.php` for a thoroughly documented one.
   Sitting in `tests/Standards/` with a `Test.php` suffix is what collects it —
   see "How a test gets collected"; there is nothing else to register.

   **One file is normally the whole answer, even when `CleanCode/ruleset.xml` configures
   the sniff.** Read the configured instance off `buildRuleset()` and pin the
   shipped `<properties>` right here, as `tests/Standards/TooManyMethodsTest.php`
   does. Add a second file at `tests/Ruleset/<Name>Test.php` only when this test
   has to *override* the shipped configuration to do its own job — see "Which
   suite a test goes in" and the `ExcessiveClassLength` pair.
5. **Wire third-party rules into `CleanCode/ruleset.xml`** when a standard is enforced by an
   existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`. Custom
   CleanCode sniffs need no ref at all — `CleanCode/Sniffs/` beside the ruleset
   is registered automatically when it loads. Pin the registration and the
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
   whole `CleanCode/ruleset.xml` parse fail and takes the entire suite down with it.

   Where a third-party sniff emits more codes than the standard being adopted,
   `<exclude>` the extra ones and pin **both halves**: that they stay silent
   through `CleanCode/ruleset.xml`, and that they still fire without the excludes.
   Otherwise a fixture that trips nothing looks exactly like a working exclude
   list. `tests/Ruleset/UndefinedVariableTest.php` is the template.
6. **Write the guideline** under `resources/boost/guidelines/<slug>.md` and
   link it from the README's standards list. A guideline opens with a `# `
   title and the rule, then carries `## Compliant` and `## Non-compliant`
   examples and a `## Enforcement` table. That table names every sniff code
   that enforces the rule and says whether `phpcbf` can fix it. A standard no
   sniff checks says it is enforced by code review only. A rule that replicates
   a **PHPMD** rule goes in the guideline that already states it. When no
   guideline does, it gets its own, named `<ruleset>-<rulename>.md`, linked
   from the README's "PHPMD rule coverage" list.
   `tests/Contract/BoostGuidelinesTest.php` fails when a loaded sniff appears
   in no guideline's table, or when a table claims the wrong fixability.

## Writing the tests

Use Pest's functional style — `it('does the thing', function () { … })` — not a
`TestCase` subclass. Where several fixtures exercise the same assertion, use a
dataset (`->with([...])`) rather than copy-pasting the test.
No subclass remains, and `vendor/bin/pest --tia` refuses to run while one
exists.

Test impact analysis (`--tia`) links a test to the PHP it executes, so it
cannot see a file a test only reads: a fixture, `CleanCode/ruleset.xml`, or a
guideline. `tests/Pest.php` therefore re-runs the whole suite when a file
outside the graph changes. `phpunit.xml.dist` excludes the fixture directories
from the source scope, so a fixture that a test executes falls under the same
rule. Code that runs in a `phpcs` or `phpcbf` subprocess is not tracked
either, so every test file that starts a subprocess declares
`pest()->group('arch');`. TIA re-runs an `arch` test after any PHP change
outside `tests/` and `vendor/`.
`tests/Contract/SubprocessTestsRerunUnderTiaTest.php` fails when a file
starts a subprocess without that line.

Say *why* in the test's name, or in the issue that records the decision, whenever the
assertion encodes a judgement call: which of two overlapping sniffs owns a diagnostic, a deliberate
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
vendor/bin/pest --tia                      # only the tests a change affects
vendor/bin/phpcs --standard=CleanCode/ruleset.xml <file>   # run the standard
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

All nine stay at full severity in `CleanCode/ruleset.xml`, so a consumer's application code
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
3032, and `CleanCode.Conditionals.AvoidConditionals` alone accounts for most of
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

**No code carries a comment.** No PHP file under `CleanCode/` or `tests/` has a
comment or a docblock, and `CleanCode/ruleset.xml`, `phpcs.self.xml` and
`phpunit.xml.dist` carry only one-line group labels such as
`<!-- Conditionals -->`. The reasoning lives in the Boost guidelines
in `resources/boost/guidelines/`, and in the issues and commits that made each decision. A comment beside the code repeats
that reasoning, and nothing tests it, so it drifts in silence:
`CleanCode/ruleset.xml` shipped three false claims that way.

Two exceptions stand:

- **`phpcs:ignore`, `phpcs:disable` and `phpcs:enable` directives.** They are
  functional, not prose. A directive may carry its reason after `--`.
- **`tests/fixtures/`.** A fixture is sniff input, and several sniffs read
  comments.

`tests/Contract/NoCommentsTest.php` enforces both halves. Delete a comment
rather than moving it into a doc: git history and the linked issue keep it.

An XML comment cannot contain `--`, so a CLI flag written inside one makes the
ruleset unparseable. PHPCS then reports `Comment must not contain '--'` and
exits 3, and a suite that loads the standard hangs rather than failing. Write
"the config-set command", not the flag.
