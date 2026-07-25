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
    └── <Category>/<Name>UnitTest.php      # legacy AbstractSniffUnitTest suites
    └── <Category>/<Name>UnitTest.inc      # PHP fixture the sniff runs against
docs/standards/                            # one doc per clean-code standard
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── Standards/
│   └── <Name>Test.php                     # sniff tests (current convention)
│   └── Fixtures/<Name>Sniff/<fixture>     # one folder per sniff class
└── Rules/
    └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
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
   `MikeBronner\CleanCode\Tests\Standards`), a plain `PHPUnit\Framework\TestCase`
   that drives the real ruleset — see `tests/Standards/NotOperatorSpacingTest.php`
   as the template. It loads `rules.xml`, narrows `$ruleset->sniffs` to the sniff
   under test, and runs it over fixture files with `LocalFile`, so the assertions
   stay stable as sibling standards land. Fixture conventions:

   - Fixtures live in `tests/Standards/Fixtures/<Name>Sniff/`, one folder per
     sniff class.
   - **Separate passing and failing fixture files** — compliant and violating
     code never share a file.
   - **Separate `autofix-before.inc` and `autofix-after.inc`** files: the former
     is the fixer's input, the latter the expected output. Omit both only when
     the rule is genuinely not auto-fixable, and pin that with a test asserting
     `getFixableCount()` is zero.

   Do **not** use `AbstractSniffUnitTest` for new sniffs. It hardcodes its
   fixture paths (`CleanCode/Tests/<Category>/<Name>UnitTest.inc`) and forces the
   autofix expectation to `<input>.fixed`, so it cannot express the layout above.
   The existing `CleanCode/Tests/` suites predate this convention and are kept
   as-is.
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
