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
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
└── Standards/
    ├── <Name>Test.php                     # sniff tests — the current convention
    └── Fixtures/<Name>Sniff/              # one folder per sniff class
        ├── passing.inc                    # compliant code — zero violations
        ├── failing.inc                    # violating code — one line map per case
        ├── autofix-before.inc             # fixer input   (fixable sniffs only)
        └── autofix-after.inc              # expected output
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
   `MikeBronner\CleanCode\Tests\Standards`), a plain PHPUnit `TestCase` that
   loads `rules.xml`, narrows `$ruleset->sniffs` to the sniff under test, and
   runs it over fixtures via `LocalFile` — see
   `tests/Standards/NotOperatorSpacingTest.php` as the template. Fixtures go in
   `tests/Standards/Fixtures/<Name>Sniff/`, with compliant and violating code
   in **separate** files (`passing.inc`, `failing.inc`) and, for a fixable
   sniff, **separate** `autofix-before.inc` / `autofix-after.inc` files. Cover
   positive, negative, boundary, and edge cases.

   Do **not** use `AbstractSniffUnitTest` for new sniffs: it hardcodes its
   fixture paths (`CleanCode/Tests/<Category>/<Name>UnitTest.inc`) and forces
   the autofix expectation to `<input>.fixed`, so it cannot express the layout
   above. The suites under `CleanCode/Tests/` predate this convention and are
   still wired up by `tests/bootstrap.php`; leave them be.
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
