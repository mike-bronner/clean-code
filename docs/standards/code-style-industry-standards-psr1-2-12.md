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
- **Fixer** — fixtures with a `.fixed` counterpart are run through PHPCS's
  fixer and compared verbatim, verifying `phpcbf` support.

Fixtures use the `.inc` extension so the repo's PSR-12 self-lint
(`composer lint`) skips their deliberate violations.
