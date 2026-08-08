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
- [Classes: Contracts (Interfaces)](docs/standards/classes-contracts-interfaces.md) — Tier 3, no rule: whether a class needs a contract depends on how it is instantiated and consumed across the codebase, which a single-file token stream cannot see, so the whole standard stays with code review ([#10](https://github.com/mike-bronner/phpcs-rules/issues/10))
- [Classes: No Statics](docs/standards/classes-no-statics.md) — Tier 2, custom sniff `CleanCode.Classes.DisallowStaticMembers`: flags static method/property declarations in any class/interface/trait/enum, detection-only ([#19](https://github.com/mike-bronner/phpcs-rules/issues/19))
- [Clear Code: One Thought Per Line](docs/standards/clear-code-one-thought-per-line.md) — Tier 2, custom sniff `CleanCode.ClearCode.OneThoughtPerLine`: one access operator per chain per line, auto-fixable ([#7](https://github.com/mike-bronner/phpcs-rules/issues/7))
- [Code Style: Industry Standards (PSR1/2/12)](docs/standards/code-style-industry-standards-psr1-2-12.md) — Tier 1, bundled standard: PHPCS `PSR12` (includes PSR1; supersedes PSR2) wired into `rules.xml` ([#49](https://github.com/mike-bronner/phpcs-rules/issues/49))
- [Code Style: Multiline Strings (HEREDOC)](docs/standards/code-style-multiline-strings-heredoc.md) — Tier 2, custom sniff `CleanCode.Strings.MultilineStrings`: flags multi-line quoted strings (auto-fixed to HEREDOC/NOWDOC) and multi-line quoted-string concatenation (detection-only) ([#53](https://github.com/mike-bronner/phpcs-rules/issues/53))
- [Conditionals: Avoid Conditionals](docs/standards/conditionals-avoid-conditionals.md) — Tier 2, custom sniff `CleanCode.Conditionals.AvoidConditionals`: one warning per if/elseif/ternary/switch, detection only, plus `SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn` lowered to warning for the auto-fixable boolean-return `if`; whether a branch was avoidable stays with code review ([#12](https://github.com/mike-bronner/phpcs-rules/issues/12))
- [Conditionals: Combine Where Possible](docs/standards/conditionals-combine-where-possible.md) — Tier 2, custom sniff: adjacent identical-branch detection ([#181](https://github.com/mike-bronner/phpcs-rules/issues/181))
- [Conditionals: Mapping Arrays](docs/standards/conditionals-mapping-arrays.md) — Tier 2, custom sniff: same-variable if/elseif chain detection ([#163](https://github.com/mike-bronner/phpcs-rules/issues/163))
- [Conditionals: No Inline If-Statements](docs/standards/conditionals-no-inline-if-statements.md) — Tier 1, existing sniff: Generic.ControlStructures.InlineControlStructure, auto-fixable ([#9](https://github.com/mike-bronner/phpcs-rules/issues/9))
- [Conditionals: One Condition Per Line](docs/standards/conditionals-one-condition-per-line.md) — Tier 2, custom sniff: auto-fixable condition-layout enforcement ([#17](https://github.com/mike-bronner/phpcs-rules/issues/17))
- [Constructors: Primary + Named Constructors](docs/standards/constructors-primary-named-constructors.md) — Tier 2, custom sniffs: named-constructor delegation check ([#184](https://github.com/mike-bronner/phpcs-rules/issues/184)) + combined-constructor detection ([#193](https://github.com/mike-bronner/phpcs-rules/issues/193))
- [Constructors: Property Promotion](docs/standards/constructors-property-promotion.md) — Tier 1, Slevomat `Classes.RequireConstructorPropertyPromotion`, auto-fixable ([#47](https://github.com/mike-bronner/phpcs-rules/issues/47))
- [Controllers: No Business Logic](docs/standards/controllers-no-business-logic.md) — Tier 3, code review only: "business logic" is a semantic judgement and the prescribed extraction to Form Request / Response classes is cross-file, so no single-file sniff can decide it; the one token-visible slice (non-RESTful public method names) is tracked as follow-up sniff issue [#141](https://github.com/mike-bronner/phpcs-rules/issues/141) ([#48](https://github.com/mike-bronner/phpcs-rules/issues/48))
- [Controllers: Route Model Binding](docs/standards/controllers-route-model-binding.md) — Tier 2, custom sniff `CleanCode.Controllers.ManualModelResolution`: warns when a public `*Controller` method resolves a model by hand (`Model::find($id)` / `findOrFail($id)`) from one of its own parameters instead of type-hinting the model and letting route-model binding resolve it, detection-only ([#50](https://github.com/mike-bronner/phpcs-rules/issues/50), superseding [#169](https://github.com/mike-bronner/phpcs-rules/issues/169))
- [Exceptions](docs/standards/exceptions.md) — Tier 2, **enforced** via Slevomat rules: `\Throwable`-only catches + non-capturing catch, both auto-fixable ([#63](https://github.com/mike-bronner/phpcs-rules/issues/63))
- [Line Length](docs/standards/line-length.md) — Tier 1, configured rule: `Generic.Files.LineLength` warns above 100 characters, errors above 120 ([#3](https://github.com/mike-bronner/phpcs-rules/issues/3))
- [Models: Eager Loading](docs/standards/models-eager-loading.md) — Tier 2, custom sniff `CleanCode.Models.RequireLazyLoadingPrevention`: warns when the application service provider never calls `Model::preventLazyLoading()` (or `Model::shouldBeStrict()`), leaving Laravel's runtime lazy-loading safety check switched off, detection-only ([#154](https://github.com/mike-bronner/phpcs-rules/issues/154)); the non-empty `$with` slice has its own focused sniff issue ([#153](https://github.com/mike-bronner/phpcs-rules/issues/153))
- [Models: Persistence Methods (Repository Pattern)](docs/standards/models-persistence-methods-repository-pattern.md) — Tier 2, custom sniff `CleanCode.Models.DisallowExternalPersistenceCalls`: warns on generic CRUD calls (`create`/`delete`/`save`/`update`) on any receiver other than `$this`, detection-only ([#37](https://github.com/mike-bronner/phpcs-rules/issues/37))
- [Naming: Casing Conventions](docs/standards/naming-casing-conventions.md) — Tier 1, existing sniffs: camelCase variables/properties/methods, PascalCase classes ([#22](https://github.com/mike-bronner/phpcs-rules/issues/22))
- [Naming: Semantic naming principles](docs/standards/naming-semantic-naming-principles.md) — Tier 3, no rule: whether a name reveals its intent, misleads, or keeps one word per concept is a judgement about meaning that a single file's token stream cannot make, so the semantic core stays with code review ([#18](https://github.com/mike-bronner/phpcs-rules/issues/18)); the one token-catchable slice, magic numbers in place of named constants, has its own focused sniff issue ([#136](https://github.com/mike-bronner/phpcs-rules/issues/136))
- [Pattern: Don't Repeat Yourself (DRY)](docs/standards/pattern-dont-repeat-yourself-dry.md) — Tier 2, custom sniff: repeated-block detection ([#134](https://github.com/mike-bronner/phpcs-rules/issues/134))
- [Policies: Secure Front- and Back-Ends](docs/standards/policies-secure-front-and-back-ends.md) — Tier 3, no rule: front-end restrictions live outside the linted token stream, and a missing back-end check is an absence a single-file sniff cannot distinguish from a guard placed in another file, so the whole standard stays with code review ([#52](https://github.com/mike-bronner/phpcs-rules/issues/52))
- [Testing: Development Process (TDD)](docs/standards/testing-development-process-tdd.md) — Tier 3, not statically enforceable: test-first order and the Red/Green/Refactor cycle are facts about the process, not the tokens, so the standard stays with code review ([#57](https://github.com/mike-bronner/phpcs-rules/issues/57)); two token-visible slices are queued as focused follow-ups ([#128](https://github.com/mike-bronner/phpcs-rules/issues/128), [#129](https://github.com/mike-bronner/phpcs-rules/issues/129))
- [Type Hints and Return Types](docs/standards/type-hints-and-return-types.md) — Tier 1, Slevomat `TypeHints.ParameterTypeHint` / `ReturnTypeHint` / `PropertyTypeHint`, partly auto-fixable ([#45](https://github.com/mike-bronner/phpcs-rules/issues/45))
- [Use Statements: No Unused Entries](docs/standards/use-statements-no-unused-entries.md) — Tier 1, Slevomat `Namespaces.UnusedUses`, auto-fixable ([#68](https://github.com/mike-bronner/phpcs-rules/issues/68))
- [Use Statements: Sort Alphabetically](docs/standards/use-statements-sort-alphabetically.md) — Tier 1, Slevomat `Namespaces.AlphabeticallySortedUses` plus `DisallowGroupUse` + `MultipleUsesPerLine` to close the group-use and comma-separated bypasses, auto-fixable for flat blocks ([#67](https://github.com/mike-bronner/phpcs-rules/issues/67))

## PHPMD rule coverage

`rules.xml` also replicates PHPMD rules, so a project running this ruleset does
not need to run `phpmd` separately for them. Each mapping is documented under
[`docs/phpmd/`](docs/phpmd/).

- [CleanCode: BooleanArgumentFlag](docs/phpmd/cleancode-booleanargumentflag.md) — Tier 2, custom sniff `CleanCode.Functions.DisallowBooleanArgumentFlag`, error severity, report-only; stricter than PHPMD on three shapes ([#76](https://github.com/mike-bronner/phpcs-rules/issues/76))
- [CleanCode: UndefinedVariable](docs/phpmd/cleancode-undefinedvariable.md) — Tier 1, external sniff `VariableAnalysis.CodeAnalysis.VariableAnalysis` raised to error severity, report-only; stricter than PHPMD on two shapes, and one shape neither tool catches ([#85](https://github.com/mike-bronner/phpcs-rules/issues/85))
- [Design: DevelopmentCodeFragment](docs/phpmd/design-developmentcodefragment.md) — Tier 1, existing sniff `CleanCode.Debug.DisallowDebugFunctions` extended to PHPMD's `unwanted-functions` defaults, report-only ([#86](https://github.com/mike-bronner/phpcs-rules/issues/86))
- [Design: EvalExpression](docs/phpmd/design-evalexpression.md) — Tier 1, existing sniff `Squiz.PHP.Eval` raised to error severity, report-only ([#107](https://github.com/mike-bronner/phpcs-rules/issues/107))
- [UnusedCode: UnusedLocalVariable](docs/phpmd/unusedcode-unusedlocalvariable.md) — Tier 1, the `UnusedVariable` code on the same external sniff `VariableAnalysis.CodeAnalysis.VariableAnalysis`, raised to error severity, report-only; three properties configured to match PHPMD, and one report per assignment where PHPMD reports one per name ([#118](https://github.com/mike-bronner/phpcs-rules/issues/118))
