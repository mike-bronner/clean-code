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
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── ThirdPartyStandards.php                # installed_paths for the standards rules.xml refs
├── Integration/                           # whole-ruleset tests (no per-sniff narrowing)
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
├── Ruleset/
│   └── <Name>Test.php + Fixtures/<Name>/  # third-party rules wired into rules.xml
│                                          # same fixture names as Standards/ below
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

   **These four names are the contract for a sniff whether it is custom or
   third-party** — a rule wired into `rules.xml` under step 3 names its
   fixtures the same way, in `tests/Ruleset/Fixtures/<Name>/`. A rule may add
   further fixtures for shapes that fit neither set (boundary cases, excluded
   codes, a documented divergence), but `passing.inc` and `failing.inc` are
   always present and always separate.

   A rule that is *not* fixable ships no `autofix-after.inc`. Prove that by
   running the fixer over `failing.inc` and asserting its output is
   byte-identical to the input — measure the claim rather than leaving the
   case untested. `tests/Ruleset/UndefinedVariableTest.php` is the template
   for both this and the fixture naming above.

   `tests/Ruleset/` folders written before this paragraph landed still use
   `compliant.inc` / `violations.inc` and a shared `violations.inc.fixed`; new
   ones use the four names here.
3. **Wire Slevomat (or other third-party) rules into `rules.xml`** when a
   standard is enforced by an existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`.
   Custom CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line. Pin the configured
   thresholds/behaviour with a test at `tests/Rules/<Name>RulesTest.php` that
   runs `rules.xml` against fixtures via PHPCS's API — see
   `tests/Rules/LineLengthRulesTest.php` as the template.

   Adding a rule from a **new** package (not Slevomat) also means adding its
   path to `MikeBronner\CleanCode\Tests\ThirdPartyStandards::installedPaths()`.
   Every suite builds its `Config` through `ConfigDouble`, which blanks the
   `installed_paths` the Composer installer wrote — so each one restores the
   value from that single helper, and a sniff missing from it fails to resolve
   across the whole suite, not just in the new test.

   Where a rule's configuration *narrows* a third-party sniff (excluding codes
   that belong to another standard, say), pin both halves: that the excluded
   codes stay silent through `rules.xml`, and that the same fixture raises them
   through the bare standard. Without the second half the first passes even if
   the fixture triggers nothing — see `tests/Ruleset/UndefinedVariableTest.php`.
4. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there.

## Running the checks

```bash
composer install
composer test    # sniff unit-test suite (PHPUnit + PHPCS harness)
composer lint    # PSR-12 self-lint of the sniff/test code
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```
