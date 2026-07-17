# phpcs-rules
PHPCS linter rules for all coding standards defined in https://mikebronner.dev/clean-code.

## Installation

```bash
composer require --dev mike-bronner/phpcs-rules
```

The package is a `phpcodesniffer-standard`, so the **CleanCode** standard
auto-registers with PHP_CodeSniffer on install (via the dealerdirect composer
installer) — `vendor/bin/phpcs -i` lists it.

## Usage

Run the master ruleset (`rules.xml`), which wires together the CleanCode
sniffs and any referenced Slevomat rules:

```bash
vendor/bin/phpcs --standard=vendor/mike-bronner/phpcs-rules/rules.xml src/
```

Or reference it from your project's own `phpcs.xml.dist`:

```xml
<rule ref="vendor/mike-bronner/phpcs-rules/rules.xml"/>
```

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for the package layout, how a new
sniff/standard plugs into `rules.xml`, and how to add its unit test.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Standards
that a token-based sniff cannot verify (Tier 3) are enforced by code review and
developer discipline rather than a PHPCS rule.

- [Arrays: Convert To Collection](docs/standards/arrays-convert-to-collection.md) — Tier 2, custom sniff: native array-function call detection ([#165](https://github.com/mike-bronner/phpcs-rules/issues/165))
- [Conditionals: Combine Where Possible](docs/standards/conditionals-combine-where-possible.md) — Tier 2, custom sniff: adjacent identical-branch detection ([#181](https://github.com/mike-bronner/phpcs-rules/issues/181))
- [Conditionals: Mapping Arrays](docs/standards/conditionals-mapping-arrays.md) — Tier 2, custom sniff: same-variable if/elseif chain detection ([#163](https://github.com/mike-bronner/phpcs-rules/issues/163))
- [Constructors: Primary + Named Constructors](docs/standards/constructors-primary-named-constructors.md) — Tier 2, custom sniffs: named-constructor delegation check ([#184](https://github.com/mike-bronner/phpcs-rules/issues/184)) + combined-constructor detection ([#193](https://github.com/mike-bronner/phpcs-rules/issues/193))
- [Indentation: Multi-Line Statements](docs/standards/indentation-multi-line-statements.md) — Tier 1, custom sniff: `CleanCode.WhiteSpace.MultiLineStatementIndent`, auto-fixable ([#38](https://github.com/mike-bronner/phpcs-rules/issues/38))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
