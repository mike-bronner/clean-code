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
    └── <Category>/<Name>UnitTest.php      # legacy AbstractSniffUnitTest cases
docs/standards/                            # one doc per clean-code standard
tests/
├── bootstrap.php                          # wires CleanCode into PHPCS's test harness
├── Rules/
│   └── <Name>RulesTest.php + Fixtures/    # master-ruleset (rules.xml) configuration tests
└── Standards/
    ├── <Name>Test.php                     # custom-sniff tests (the current convention)
    └── Fixtures/<Name>Sniff/              # passing.inc, failing.inc,
                                           # autofix-before.inc, autofix-after.inc
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
   fixtures in `tests/Standards/Fixtures/<Name>Sniff/`. Use
   `tests/Standards/NotOperatorSpacingTest.php` as the template. Fixtures are
   **separate files per role**, never mixed:

   - `passing.inc` — compliant code, asserted to produce no violations.
   - `failing.inc` — violating code, asserted line by line with the expected
     violation codes.
   - `autofix-before.inc` / `autofix-after.inc` — the fixer's input and its
     expected output. Omit both only when the sniff is genuinely not
     auto-fixable.

   Do **not** extend `PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest`
   for new sniffs: it hardcodes its fixture paths to
   `CleanCode/Tests/<Category>/<Name>UnitTest.inc` and forces the autofix
   expectation to `<input>.fixed`, so it cannot express the layout above. The
   `CleanCode/Tests/` cases predate this convention and stay where they are.
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
