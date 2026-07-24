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
    └── <Category>/<Name>UnitTest.php      # legacy AbstractSniffUnitTest tests
    └── <Category>/<Name>UnitTest.inc      # PHP fixture the sniff runs against
docs/standards/                            # one doc per clean-code standard
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # third-party rule configuration tests
├── Ruleset/
│   └── <Name>Test.php + Fixtures/         # custom-sniff tests (current convention)
└── Standards/
    └── <Name>Test.php + Fixtures/         # custom-sniff tests (current convention)
```

## Adding a new sniff

1. **Create the sniff** at `CleanCode/Sniffs/<Category>/<Name>Sniff.php`, in the
   namespace `MikeBronner\CleanCode\Sniffs\<Category>`, implementing
   `PHP_CodeSniffer\Sniffs\Sniff`. Its code becomes
   `CleanCode.<Category>.<Name>`. Use
   `CleanCode/Sniffs/Debug/DisallowDebugFunctionsSniff.php` as the template.
   Sniffs in the standard's `Sniffs/` directory are included automatically —
   no per-sniff registration in `CleanCode/ruleset.xml` is needed.
2. **Add its test** at `tests/Ruleset/<Name>Test.php` (namespace
   `MikeBronner\CleanCode\Tests\Ruleset`), driving the real PHPCS API —
   load `rules.xml` through a `ConfigDouble`, narrow `$ruleset->sniffs` to the
   sniff under test, and run a `LocalFile` over each fixture. Use
   `tests/Ruleset/DisallowTypeIntrospectionTest.php` as the template. Assert
   exact line/column/source tuples, not just counts.

   Fixtures go in `tests/Ruleset/Fixtures/<Name>Sniff/` — a folder named for
   the sniff class, holding **separate** files per case:

   - `compliant.inc` — code the sniff must leave alone (zero violations).
   - one or more violation fixtures — passing and failing code never share a
     file.
   - `autofix-before.inc` / `autofix-after.inc` — the fixer's input and its
     expected output, when the sniff is fixable. Omit both for a
     detection-only sniff and say so in the PR.

   Do **not** use `PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest` for
   new sniffs. It hardcodes its fixture paths to
   `CleanCode/Tests/<Category>/<Name>UnitTest.inc` and forces the autofix
   expectation to `<input>.fixed`, so it cannot express the layout above. The
   four `CleanCode/Tests/**/*UnitTest.php` classes still on that harness are
   legacy. Test classes are auto-discovered — nothing to register.
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
