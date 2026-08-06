# Contributing

This package is a PHP_CodeSniffer standard (`composer.json` type
`phpcodesniffer-standard`). Custom sniffs live in the **CleanCode** standard;
the master ruleset consumers run is **`rules.xml`** at the package root.

## Layout

```
rules.xml                                  # master ruleset — standards get wired in here
CleanCode/
├── ruleset.xml                            # the installable CleanCode standard
├── Sniffs/
│   └── <Category>/<Name>Sniff.php         # one sniff per file
└── Tests/
    └── <Category>/<Name>UnitTest.php      # expected error/warning line maps
    └── <Category>/<Name>UnitTest.inc      # PHP fixture the sniff runs against
docs/standards/                            # one doc per clean-code standard
docs/phpmd/                                # one doc per replicated PHPMD rule
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
└── Standards/
    ├── <Name>Test.php                     # custom-sniff tests (see step 2 below)
    └── Fixtures/<Name>Sniff/              # passing.inc, failing.inc, autofix-before/after.inc
```

## Adding a new sniff

1. **Create the sniff** at `CleanCode/Sniffs/<Category>/<Name>Sniff.php`, in the
   namespace `MikeBronner\CleanCode\Sniffs\<Category>`, implementing
   `PHP_CodeSniffer\Sniffs\Sniff`. Its code becomes
   `CleanCode.<Category>.<Name>`. Use
   `CleanCode/Sniffs/Debug/DisallowDebugFunctionsSniff.php` as the template.
   Sniffs in the standard's `Sniffs/` directory are included automatically —
   no per-sniff registration in `CleanCode/ruleset.xml` is needed.
2. **Add its test** at `tests/Standards/<Name>Test.php` (namespace
   `MikeBronner\CleanCode\Tests\Standards`), driving the real `phpcs` /
   `phpcbf` through PHPCS's API — see `tests/Standards/NotOperatorSpacingTest.php`
   as the template. Do **not** extend
   `PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest`: it hardcodes its
   fixture paths (`Tests/<Category>/<Name>UnitTest.inc`) and forces the autofix
   expectation to `<input>.fixed`, so it cannot express the fixture layout
   below. Tests are auto-discovered by PHPUnit — nothing to register.

   Fixtures live at `tests/Standards/Fixtures/<SniffClassName>/` — the
   `Fixtures` folder, then a folder named for the sniff class — with compliant
   and violating code in **separate files** (`passing.inc` / `failing.inc`),
   never sharing one. If the sniff is fixable, the fixer's input and expected
   output are likewise **separate files**: `autofix-before.inc` and
   `autofix-after.inc`. Sniffs added before this convention landed still use
   the older `CleanCode/Tests/` + `AbstractSniffUnitTest` layout; new ones
   follow the layout here.
3. **Wire Slevomat (or other third-party) rules into `rules.xml`** when a
   standard is enforced by an existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`.
   Custom CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line. Pin the configured
   thresholds/behaviour with a test at `tests/Rules/<Name>RulesTest.php` that
   runs `rules.xml` against fixtures via PHPCS's API — see
   `tests/Rules/LineLengthRulesTest.php` as the template.
4. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there. A rule that replicates a **PHPMD**
   rule rather than a mikebronner.dev clean-code standard is documented under
   `docs/phpmd/<ruleset>-<rulename>.md` instead, and linked from the README's
   "PHPMD rule coverage" list — the mapping (PHPMD rule → PHPCS sniff) is what
   the doc has to state, so `phpmd` no longer needs to run for that rule.

## Running the checks

```bash
composer install
composer test    # sniff unit-test suite (PHPUnit + PHPCS harness)
composer lint    # PSR-12 self-lint of the sniff/test code
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```
