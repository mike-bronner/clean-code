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
- [Arrays: Operator Spacing & Line Breaks](docs/standards/arrays-operator-spacing-and-line-breaks.md) — Tier 1, configured `Squiz.WhiteSpace.OperatorSpacing` + `Squiz.Strings.ConcatenationSpacing` (exactly one space each side, auto-fixable) plus custom sniffs `CleanCode.Operators.NotOperatorSpacing` (auto-fixable) and `CleanCode.Operators.OperatorLineBreak` (reporting only) ([#35](https://github.com/mike-bronner/phpcs-rules/issues/35))
- [Blank Lines](docs/standards/blank-lines.md) — Tier 1, custom sniff: `CleanCode.WhiteSpace.BlankLines`, auto-fixable ([#43](https://github.com/mike-bronner/phpcs-rules/issues/43))
- [Classes: No Statics](docs/standards/classes-no-statics.md) — Tier 2, custom sniff `CleanCode.Classes.DisallowStaticMembers`: flags static method/property declarations in any class/interface/trait/enum, detection-only ([#19](https://github.com/mike-bronner/phpcs-rules/issues/19))
- [Clear Code: One Thought Per Line](docs/standards/clear-code-one-thought-per-line.md) — Tier 2, custom sniff `CleanCode.ClearCode.OneThoughtPerLine`: one access operator per chain per line, auto-fixable ([#7](https://github.com/mike-bronner/phpcs-rules/issues/7))
- [Code Style: Industry Standards (PSR1/2/12)](docs/standards/code-style-industry-standards-psr1-2-12.md) — Tier 1, bundled standard: PHPCS `PSR12` (includes PSR1; supersedes PSR2) wired into `rules.xml` ([#49](https://github.com/mike-bronner/phpcs-rules/issues/49))
- [Code Style: Multiline Strings (HEREDOC)](docs/standards/code-style-multiline-strings-heredoc.md) — Tier 2, custom sniff `CleanCode.Strings.MultilineStrings`: flags multi-line quoted strings (auto-fixed to HEREDOC/NOWDOC) and multi-line quoted-string concatenation (detection-only) ([#53](https://github.com/mike-bronner/phpcs-rules/issues/53))
- [Conditionals: Combine Where Possible](docs/standards/conditionals-combine-where-possible.md) — Tier 2, custom sniff: adjacent identical-branch detection ([#181](https://github.com/mike-bronner/phpcs-rules/issues/181))
- [Conditionals: Mapping Arrays](docs/standards/conditionals-mapping-arrays.md) — Tier 2, custom sniff: same-variable if/elseif chain detection ([#163](https://github.com/mike-bronner/phpcs-rules/issues/163))
- [Conditionals: No Inline If-Statements](docs/standards/conditionals-no-inline-if-statements.md) — Tier 1, existing sniff: Generic.ControlStructures.InlineControlStructure, auto-fixable ([#9](https://github.com/mike-bronner/phpcs-rules/issues/9))
- [Conditionals: One Condition Per Line](docs/standards/conditionals-one-condition-per-line.md) — Tier 2, custom sniff: auto-fixable condition-layout enforcement ([#17](https://github.com/mike-bronner/phpcs-rules/issues/17))
- [Constructors: Primary + Named Constructors](docs/standards/constructors-primary-named-constructors.md) — Tier 2, custom sniffs: named-constructor delegation check ([#184](https://github.com/mike-bronner/phpcs-rules/issues/184)) + combined-constructor detection ([#193](https://github.com/mike-bronner/phpcs-rules/issues/193))
- [Constructors: Property Promotion](docs/standards/constructors-property-promotion.md) — Tier 1, Slevomat `Classes.RequireConstructorPropertyPromotion`, auto-fixable ([#47](https://github.com/mike-bronner/phpcs-rules/issues/47))
- [Exceptions](docs/standards/exceptions.md) — Tier 2, **enforced** via Slevomat rules: `\Throwable`-only catches + non-capturing catch, both auto-fixable ([#63](https://github.com/mike-bronner/phpcs-rules/issues/63))
- [Line Length](docs/standards/line-length.md) — Tier 1, configured rule: `Generic.Files.LineLength` warns above 100 characters, errors above 120 ([#3](https://github.com/mike-bronner/phpcs-rules/issues/3))
- [Naming: Casing Conventions](docs/standards/naming-casing-conventions.md) — Tier 1, existing sniffs: camelCase variables/properties/methods, PascalCase classes ([#22](https://github.com/mike-bronner/phpcs-rules/issues/22))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
- [Type Hints and Return Types](docs/standards/type-hints-and-return-types.md) — Tier 1, Slevomat `TypeHints.ParameterTypeHint` / `ReturnTypeHint` / `PropertyTypeHint`, partly auto-fixable ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45))
- [Use Statements: No Unused Entries](docs/standards/use-statements-no-unused-entries.md) — Tier 1, Slevomat `Namespaces.UnusedUses`, auto-fixable ([#68](https://github.com/mike-bronner/phpcs-rules/issues/68))
