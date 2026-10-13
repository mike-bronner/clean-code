---
name: clean-code
description: "Clean code rules for PHP and Laravel, enforced by the CleanCode PHPCS standard from mike-bronner/clean-code. Activate before you write, edit, refactor or review any PHP or Blade code, and when you fix a CleanCode phpcs violation."
---

# Clean Code

These rules govern every PHP and Blade file in the project. The `CleanCode`
PHPCS standard enforces many of them, and code review enforces the rest.

## How to use this skill

1. Find the references below for the code you are about to touch. A change to a
   model, for example, needs the Models references and the general ones.
2. Read each of those references in full. Each one states the rule, shows
   compliant and non-compliant code, and lists the sniffs that enforce it in an
   Enforcement table.
3. Write the code so that it matches the compliant examples.
4. Run `vendor/bin/phpcs` over the changed files, and fix every violation. A
   sniff message that names a reference file points at the rule it enforces.

A reference whose Enforcement section says "Enforced by code review only" has
no sniff. Apply it anyway.

## General principles

- [Boy Scout Rule](references/boy-scout-rule.md)
- [Debt: Mental Debt](references/debt-mental-debt.md)
- [Debt: Technical Debt](references/debt-technical-debt.md)
- [Don't Optimize Early](references/dont-optimize-early.md)
- [No Dead Code](references/no-dead-code.md)
- [Dependency Injection](references/dependency-injection.md)
- [Exceptions](references/exceptions.md)

## Clear code

- [Encapsulate Each Concept in a Method](references/clear-code-encapsulate-each-concept-in-a-method.md)
- [Encapsulate Related Classes in a Domain](references/clear-code-encapsulate-related-classes-in-a-domain.md)
- [Encapsulate Related Methods in a Class](references/clear-code-encapsulate-related-methods-in-a-class.md)
- [Group Code By Concepts](references/clear-code-group-code-by-concepts.md)
- [One Idea Per Statement](references/clear-code-one-idea-per-statement.md)
- [One Thought Per Line](references/clear-code-one-thought-per-line.md)

## Code style and layout

- [Code Style: Industry Standards (PSR1/2/12)](references/code-style-industry-standards-psr1-2-12.md)
- [Code Style: Linters (config & no auto-formatter)](references/code-style-linters-config--no-auto-formatter.md)
- [Code Style: Multiline Strings (HEREDOC)](references/code-style-multiline-strings-heredoc.md)
- [Line Length](references/line-length.md)
- [Blank Lines](references/blank-lines.md)
- [Indentation: Logical Groupings](references/indentation-logical-groupings.md)
- [Indentation: Methods (max 2 nesting levels)](references/indentation-methods-max-nesting-levels.md)
- [Indentation: Multi-Line Statements](references/indentation-multi-line-statements.md)
- [Use Statements: No Unused Entries](references/use-statements-no-unused-entries.md)
- [Use Statements: Sort Alphabetically](references/use-statements-sort-alphabetically.md)
- [Strings: Interpolation, quoting, HereDocs](references/strings-interpolation-quoting-heredocs.md)

## Naming

- [Naming: Casing Conventions](references/naming-casing-conventions.md)
- [Naming: Semantic naming principles](references/naming-semantic-naming-principles.md)
- [BooleanGetMethodName](references/naming-booleangetmethodname.md)
- [ConstructorWithNameAsEnclosingClass](references/naming-constructorwithnameasenclosingclass.md)
- [LongClassName](references/naming-longclassname.md)
- [LongVariable](references/naming-longvariable.md)

## Conditionals and operators

- [Conditionals: Avoid Conditionals](references/conditionals-avoid-conditionals.md)
- [Conditionals: Combine Where Possible](references/conditionals-combine-where-possible.md)
- [Conditionals: Mapping Arrays](references/conditionals-mapping-arrays.md)
- [Conditionals: No else or elseif](references/conditionals-no-else-or-elseif.md)
- [Conditionals: No Inline If-Statements](references/conditionals-no-inline-if-statements.md)
- [Conditionals: One Condition Per Line](references/conditionals-one-condition-per-line.md)
- [Conditionals: Ternary Conditionals](references/conditionals-ternary-conditionals.md)
- [Operators: Active](references/operators-active.md)
- [Operators: Evaluative](references/operators-evaluative.md)
- [Operators: Manipulative](references/operators-manipulative.md)
- [Operators: Passive](references/operators-passive.md)

## Arrays and collections

- [Arrays: Array Accessors (`data_get`)](references/arrays-array-accessors.md)
- [Arrays: Convert To Collection](references/arrays-convert-to-collection.md)
- [Arrays: Operator Spacing & Line Breaks](references/arrays-operator-spacing-and-line-breaks.md)
- [Collections: Only Use Collection Methods](references/collections-only-use-collection-methods.md)

## Classes, constructors and methods

- [Classes: Class Naming](references/classes-class-naming.md)
- [Classes: Contracts (Interfaces)](references/classes-contracts-interfaces.md)
- [Classes: Introspection / Type Casting](references/classes-introspection-type-casting.md)
- [Classes: No Statics](references/classes-no-statics.md)
- [Constructors: No Logic in Constructors](references/constructors-no-logic-in-constructors.md)
- [Constructors: Primary + Named Constructors](references/constructors-primary-named-constructors.md)
- [Constructors: Property Promotion](references/constructors-property-promotion.md)
- [Methods: Declared Parameters](references/methods-declared-parameters.md)
- [Methods: Naming](references/methods-naming.md)
- [Methods: No Null Arguments](references/methods-no-null-arguments.md)
- [Methods: Type Hints](references/methods-type-hints.md)
- [Type Hints and Return Types](references/type-hints-and-return-types.md)

## Patterns

- [Pattern: Don't Repeat Yourself (DRY)](references/pattern-dont-repeat-yourself-dry.md)
- [Pattern: Model-View-Controller (MVC)](references/pattern-model-view-controller-mvc.md)
- [Pattern: Repository](references/pattern-repository.md)
- [Pattern: SOLID](references/pattern-solid.md)

## Laravel

- [Controllers: No Business Logic](references/controllers-no-business-logic.md)
- [Controllers: Route Model Binding](references/controllers-route-model-binding.md)
- [Models: Eager Loading](references/models-eager-loading.md)
- [Models: Naming Conventions](references/models-naming-conventions.md)
- [Models: Organization (member ordering)](references/models-organization.md)
- [Models: Persistence Methods (Repository Pattern)](references/models-persistence-methods-repository-pattern.md)
- [Models: Relationship Properties](references/models-relationship-properties.md)
- [Models: Structure (Attributes/Queries traits)](references/models-structure-attributes-queries-traits.md)
- [Routes: Conventions (Do / Do Not)](references/routes-conventions-do-do-not.md)
- [Routes: Types (API / View)](references/routes-types-api-view.md)
- [Policies: Secure Front- and Back-Ends](references/policies-secure-front-and-back-ends.md)
- [Livewire: Components](references/livewire-components.md)

## Testing

- [Testing: Databases (SQLite caveats)](references/testing-databases-sqlite-caveats.md)
- [Testing: Development Process (TDD)](references/testing-development-process-tdd.md)
- [Testing: Guidelines](references/testing-guidelines.md)
- [Testing: Test Suites](references/testing-test-suites.md)

## PHPMD rules

Each of these replicates a PHPMD rule that no standard above already states.

- [CleanCode: BooleanArgumentFlag](references/cleancode-booleanargumentflag.md)
- [CleanCode: DuplicatedArrayKey](references/cleancode-duplicatedarraykey.md)
- [CleanCode: ErrorControlOperator](references/cleancode-errorcontroloperator.md)
- [CleanCode: IfStatementAssignment](references/cleancode-ifstatementassignment.md)
- [CleanCode: MissingImport](references/cleancode-missingimport.md)
- [CleanCode: UndefinedVariable](references/cleancode-undefinedvariable.md)
- [CodeSize: CyclomaticComplexity](references/codesize-cyclomaticcomplexity.md)
- [CodeSize: ExcessiveMethodLength](references/codesize-excessivemethodlength.md)
- [CodeSize: ExcessiveParameterList](references/codesize-excessiveparameterlist.md)
- [CodeSize: NPathComplexity](references/codesize-npathcomplexity.md)
- [Controversial: Superglobals](references/controversial-superglobals.md)
- [Design: CountInLoopExpression](references/design-countinloopexpression.md)
- [Design: DepthOfInheritance](references/design-depthofinheritance.md)
- [Design: DevelopmentCodeFragment](references/design-developmentcodefragment.md)
- [Design: EvalExpression](references/design-evalexpression.md)
- [Design: ExitExpression](references/design-exitexpression.md)
- [Design: GotoStatement](references/design-gotostatement.md)
- [Design: NumberOfChildren](references/design-numberofchildren.md)
