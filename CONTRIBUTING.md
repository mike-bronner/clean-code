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
tests/bootstrap.php                        # wires CleanCode into PHPCS's test harness
```

## Adding a new sniff

1. **Create the sniff** at `CleanCode/Sniffs/<Category>/<Name>Sniff.php`, in the
   namespace `MikeBronner\CleanCode\Sniffs\<Category>`, implementing
   `PHP_CodeSniffer\Sniffs\Sniff`. Its code becomes
   `CleanCode.<Category>.<Name>`. Use
   `CleanCode/Sniffs/Debug/DisallowDebugFunctionsSniff.php` as the template.
   Sniffs in the standard's `Sniffs/` directory are included automatically —
   no per-sniff registration in `CleanCode/ruleset.xml` is needed.
2. **Add its unit test** at `CleanCode/Tests/<Category>/<Name>UnitTest.php`
   (namespace `MikeBronner\CleanCode\Tests\<Category>`), extending
   `PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest`, plus a
   `<Name>UnitTest.inc` fixture beside it containing positive, negative, and
   edge cases. `getErrorList()` / `getWarningList()` return
   `line => expected count` maps for the fixture. If the sniff is fixable, add
   a `<Name>UnitTest.inc.fixed` file with the expected post-fix source.
   Test classes are auto-discovered by `tests/bootstrap.php` — nothing to
   register.
3. **Wire Slevomat (or other third-party) rules into `rules.xml`** when a
   standard is enforced by an existing sniff instead of a custom one, e.g.
   `<rule ref="SlevomatCodingStandard.TypeHints.DeclareStrictTypes"/>`.
   Custom CleanCode sniffs are already picked up via the
   `<rule ref="./CleanCode/ruleset.xml"/>` line.
4. **Document the standard** under `docs/standards/` and link it from the
   README, following the existing docs there.

## Running the checks

```bash
composer install
composer test    # sniff unit-test suite (PHPUnit + PHPCS harness)
composer lint    # PSR-12 self-lint of the sniff/test code
vendor/bin/phpcs --standard=rules.xml <file>   # run the master ruleset
```
