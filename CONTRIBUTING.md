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
└── Tests/                                 # legacy AbstractSniffUnitTest cases (see below)
docs/standards/                            # one doc per clean-code standard
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
└── Standards/
    ├── <Name>Test.php                     # one test per custom sniff
    └── Fixtures/<Name>Sniff/              # passing.inc, failing.inc, autofix-before.inc, …
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
   `MikeBronner\CleanCode\Tests\Standards`), driving the real PHPCS API against
   fixtures — see `tests/Standards/OperatorLineBreakTest.php` as the template.
   Fixtures go in `tests/Standards/Fixtures/<Name>Sniff/`, one folder per sniff
   class, with **separate files for compliant and violating code**
   (`passing.inc` / `failing.inc` — never both in one file) plus any edge-case
   files the sniff needs. If the sniff is fixable, add
   `autofix-before.inc` (the fixer's input) and `autofix-after.inc` (the
   expected output); if it is not, say so in the test and pin the decision with
   an assertion that nothing is fixable.

   Do **not** extend `PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest`:
   it hardcodes its fixture paths to `Tests/<Category>/<Name>UnitTest.inc` and
   forces the autofix expectation to `<input>.fixed`, so it cannot express the
   layout above. The `CleanCode/Tests/` cases predate this convention and are
   still discovered by `tests/bootstrap.php`; new sniffs do not go there.
3. **Wire Slevomat (or other third-party) rules into `rules.xml`** when a
   standard is enforced by an existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`.
   Custom CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line. Pin the configured
   thresholds/behaviour with a test at `tests/Rules/<Name>RulesTest.php` that
   runs `rules.xml` against fixtures via PHPCS's API — see
   `tests/Rules/LineLengthRulesTest.php` as the template.
4. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there.

## Running the checks

```bash
composer install
composer test    # sniff unit-test suite (PHPUnit + PHPCS harness)
composer lint    # PSR-12 self-lint of the sniff/test code
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```
