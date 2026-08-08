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
sniff/standard plugs into `rules.xml`, the test-fixture contract, and how to
add its Pest test.

## Standards

Each standard is documented under [`docs/standards/`](docs/standards/). Every
entry below carries the tier assigned in that document's *Enforceability*
section, and summarizes how the standard is enforced.

- **Tier 1** and **Tier 2** standards are enforced automatically, by rules wired
  into the master `rules.xml`: a bundled standard, an existing PHPCS or Slevomat
  rule (configured where needed), a custom `CleanCode` sniff, or a combination
  of these.
- **Tier 3** standards are the ones a token-based sniff cannot verify. They are
  enforced by code review and developer discipline rather than a PHPCS rule.

Where a rule covers only part of a standard, that standard's document records
what stays with code review.

- [Arrays: Array Accessors (`data_get`)](docs/standards/arrays-array-accessors.md) — Tier 2, custom sniff `CleanCode.Arrays.ArrayAccessors`: flags direct element (`$array['key']`) and property (`$object->property`) reads once per accessor chain, leaving write-side access, existence checks, array literals, `$this`-rooted access, and method calls alone, detection-only ([#33](https://github.com/mike-bronner/phpcs-rules/issues/33))
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
- [Models: Eager Loading](docs/standards/models-eager-loading.md) — Tier 2, custom sniff `CleanCode.Models.RequireLazyLoadingPrevention`: warns when the application service provider never calls `Model::preventLazyLoading()` (or `Model::shouldBeStrict()`), leaving Laravel's runtime lazy-loading safety check switched off, detection-only ([#154](https://github.com/mike-bronner/phpcs-rules/issues/154)); the non-empty `$with` slice has its own focused sniff issue ([#153](https://github.com/mike-bronner/phpcs-rules/issues/153))
- [Models: Persistence Methods (Repository Pattern)](docs/standards/models-persistence-methods-repository-pattern.md) — Tier 2, custom sniff `CleanCode.Models.DisallowExternalPersistenceCalls`: warns on generic CRUD calls (`create`/`delete`/`save`/`update`) on any receiver other than `$this`, detection-only ([#37](https://github.com/mike-bronner/phpcs-rules/issues/37))
- [Naming: Casing Conventions](docs/standards/naming-casing-conventions.md) — Tier 1, existing sniffs: camelCase variables/properties/methods, PascalCase classes ([#22](https://github.com/mike-bronner/phpcs-rules/issues/22))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
- [Policies: Secure Front- and Back-Ends](docs/standards/policies-secure-front-and-back-ends.md) — Tier 3, no rule: front-end restrictions live outside the linted token stream, and a missing back-end check is an absence a single-file sniff cannot distinguish from a guard placed in another file, so the whole standard stays with code review ([#52](https://github.com/mike-bronner/phpcs-rules/issues/52))
- [Type Hints and Return Types](docs/standards/type-hints-and-return-types.md) — Tier 1, Slevomat `TypeHints.ParameterTypeHint` / `ReturnTypeHint` / `PropertyTypeHint`, partly auto-fixable ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45))
- [Use Statements: No Unused Entries](docs/standards/use-statements-no-unused-entries.md) — Tier 1, Slevomat `Namespaces.UnusedUses`, auto-fixable ([#68](https://github.com/mike-bronner/phpcs-rules/issues/68))

## PHPMD rule coverage

`rules.xml` also replicates PHPMD rules, so a project running this ruleset does
not need to run `phpmd` separately for them. Each mapping is documented under
[`docs/phpmd/`](docs/phpmd/).

- [CleanCode: BooleanArgumentFlag](docs/phpmd/cleancode-booleanargumentflag.md) — Tier 2, custom sniff `CleanCode.Functions.DisallowBooleanArgumentFlag`, error severity, report-only; stricter than PHPMD on three shapes ([#76](https://github.com/mike-bronner/phpcs-rules/issues/76))
- [CleanCode: UndefinedVariable](docs/phpmd/cleancode-undefinedvariable.md) — Tier 1, external sniff `VariableAnalysis.CodeAnalysis.VariableAnalysis` raised to error severity, report-only; stricter than PHPMD on two shapes, and one shape neither tool catches ([#85](https://github.com/mike-bronner/phpcs-rules/issues/85))
- [Design: EvalExpression](docs/phpmd/design-evalexpression.md) — Tier 1, existing sniff `Squiz.PHP.Eval` raised to error severity, report-only ([#107](https://github.com/mike-bronner/phpcs-rules/issues/107))
