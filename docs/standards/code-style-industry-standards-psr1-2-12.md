# Code Style: Industry Standards (PSR1/2/12)

## Standard

All code style must adhere to the following PHP standards:

- [PSR-1](https://www.php-fig.org/psr/psr-1/) — basic coding standard: PHP
  tags, UTF-8 without BOM, declarations vs. side effects, namespace and class
  naming, constant and method naming.
- [PSR-2](https://www.php-fig.org/psr/psr-2/) — coding style guide
  (superseded by PSR-12, whose rules incorporate and update it).
- [PSR-12](https://www.php-fig.org/psr/psr-12/) — extended coding style:
  files and lines, declare statements, namespace and import formatting,
  classes, properties, methods, control structures, operators, and closures.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (bundled standards)

Fully enforced by PHPCS's bundled `PSR12` standard, wired into the master
`rules.xml` as-is ([#49](https://github.com/mike-bronner/phpcs-rules/issues/49)):

```xml
<rule ref="PSR12"/>
```

- **PSR-1** is included wholesale — the bundled `PSR12` ruleset begins with
  `<rule ref="PSR1"/>`.
- **PSR-2** is not referenced separately, deliberately. PSR-12 officially
  supersedes PSR-2, and PHPCS's `PSR2` standard conflicts with `PSR12` where
  the specs diverged (e.g. multi-line function declarations place the opening
  brace differently). The PSR-2 requirements that survive live on inside the
  bundled `PSR12` ruleset (`PSR2.Files.EndFileNewline`,
  `PSR2.Files.ClosingTag`, `PSR2.Classes.PropertyDeclaration`, …).
- **Auto-fixable** — most violations are fixable via `phpcbf`; exceptions are
  advisory-only checks such as the 120-character soft line limit (reported as
  a warning) and the PSR-1 side-effects rule.

## Precedence — custom standards override the industry baseline

The `PSR12` reference sits *before* the custom clean-code rules in
`rules.xml` on purpose: PHPCS applies later same-sniff configuration over
earlier, so a custom rule that reconfigures a sniff the PSR12 bundle also
includes wins automatically. Where a custom standard contradicts a PSR12
check outright, the conflicting sniff gets carved out of the PSR12 reference
with `<exclude>`.

Verified against the currently enforced custom standards — no carve-outs are
needed today:

- **Clear Code: One Thought Per Line** — the fixer's multi-line chain style
  produces zero PSR12 violations.
- **Exceptions** — `\Throwable`-only and non-capturing catches are
  PSR12-clean.
- **Naming: Casing Conventions** — PSR12 does not include
  `Squiz.NamingConventions.ValidVariableName`, so the excluded
  `PrivateNoUnderscore` check cannot re-enter through the industry baseline
  (asserted by `CasingConventionsRulesetTest`).

`IndustryStandardsTest::testCustomStandardShapedCodeStaysPsr12Clean` pins
this contract: it lints the custom sniffs' fixer outputs with the full
master ruleset, so a future PSR12-vs-custom conflict fails the build and
gets carved out here.

## Tests

`tests/Integration/IndustryStandardsTest.php` runs the master `rules.xml`
against the fixtures in `tests/Integration/fixtures/`, asserting exact
`line => violation count` maps:

- **Positive** — fully compliant fixtures (including an abstract class with
  `abstract`/`final` method modifiers and declaration-only files) produce
  zero violations.
- **Negative** — one fixture per PSR rule area: side effects mixed with
  declarations, mixed HTML/PHP, one-class-per-file, missing namespace,
  member visibility, line length, indentation, brace placement, and
  control-structure formatting.
- **Fixer** — fixtures with a `.fixed.php` counterpart are run through PHPCS's
  fixer and compared verbatim, verifying `phpcbf` support.

Fixtures are ordinary `.php` files; the repo's PSR-12 self-lint
(`composer lint`) skips their deliberate violations by ignoring every
`fixtures/` directory.
