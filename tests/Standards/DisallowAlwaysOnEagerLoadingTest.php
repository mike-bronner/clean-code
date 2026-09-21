<?php

/**
 * Tests the custom CleanCode.Models.DisallowAlwaysOnEagerLoading sniff
 * (Models: Eager Loading, #74/#153). Fixtures live in
 * tests/fixtures/DisallowAlwaysOnEagerLoadingSniff/.
 *
 * The sniff is a presence check: a class extending a model-shaped parent, whose
 * body declares a $with property defaulting to a non-empty array literal, earns
 * one warning on that property. Both halves have to hold, so every "no
 * violations" assertion here names which half it withdraws — a negative
 * fixture alone would pass just as well against a sniff that never fires,
 * which is why the compliant and near-miss shapes sit in one file alongside a
 * failing fixture that does warn.
 *
 * Lines and columns are asserted separately because tests/Helpers.php has no
 * warning-aware tuple helper: violationTuples() reads getErrors() only, so
 * warnings take violationSourcesByLine() for the sources — as the sibling
 * RequireLazyLoadingPrevention test does — plus the raw column keys.
 *
 * The rule is detection-only: emptying $with means moving each relationship to
 * a query-site with() call the sniff cannot see or write, so there is no
 * autofixed fixture.
 *
 * CleanCode/ruleset.xml does not path-scope this sniff, so the fixtures are processed where
 * they live. The sniff is isolated from the rest of the master ruleset (loaded,
 * then $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const EAGER_LOADING_WITH = 'CleanCode.Models.DisallowAlwaysOnEagerLoading';

const EAGER_LOADING_WITH_WARNING = EAGER_LOADING_WITH . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EAGER_LOADING_WITH);
});

/**
 * Every model-shaped parent the sniff accepts, each carrying a populated
 * $with, and each reported on the property rather than the class — unlike the
 * sibling RequireLazyLoadingPrevention sniff, this defect has a line of its
 * own. One line per shape, so dropping any single one of them from the sniff
 * reddens this test on that line alone:
 *
 * - line 5, `extends Model` — the canonical Eloquent model.
 * - line 10, `extends Authenticatable` — Laravel's user model base.
 * - line 15, `extends Pivot` — a pivot model.
 * - line 20, `extends BaseModel` — the `*Model` suffix rule, and the
 *   `array('lines')` long syntax.
 * - line 25, `extends \Illuminate\Database\Eloquent\Relations\Pivot` — a
 *   namespace-qualified parent. Only the short name is compared, because the
 *   FQCN is not resolvable at lint time. The short name here is deliberately
 *   not `*Model`-suffixed: comparing the whole qualified name would still match
 *   a namespaced `Model` on the suffix rule, so only a parent like this one
 *   proves the qualifiers are actually stripped.
 * - line 30, `extends model` — a lower-cased parent. PHP class names are
 *   case-insensitive, so a consuming codebase's casing must not matter.
 * - line 35, `extends Model` again, for the third visibility keyword.
 *
 * The visibility keyword differs across the seven (protected, private, public)
 * because the standard prohibits the construct at any visibility; the sniff
 * never reads the modifier, so those three pin the AC rather than a branch.
 * The columns differ with the keyword's length for the same reason.
 */
it('flags a populated $with on every model-shaped parent', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'failing.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            5 => [EAGER_LOADING_WITH_WARNING],
            10 => [EAGER_LOADING_WITH_WARNING],
            15 => [EAGER_LOADING_WITH_WARNING],
            20 => [EAGER_LOADING_WITH_WARNING],
            25 => [EAGER_LOADING_WITH_WARNING],
            30 => [EAGER_LOADING_WITH_WARNING],
            35 => [EAGER_LOADING_WITH_WARNING],
        ])
        ->and(array_map('array_keys', $warnings))->toBe([
            5 => [15],
            10 => [15],
            15 => [15],
            20 => [15],
            25 => [15],
            30 => [13],
            35 => [12],
        ]);
});

/**
 * The message points at the query-site fix and cites the standard, so a report
 * over a whole application says what to do instead of only what is wrong.
 */
it('names the query-site alternative in the warning message', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'failing.php')->getWarnings();

    expect($warnings[5][15][0]['message'])
        ->toContain('with()')
        ->toContain('docs/standards/models-eager-loading.md');
});

/**
 * Every shape the sniff must stay silent on, in one file. Each withdraws one
 * half of the detection, and removing the matching condition from the sniff
 * turns this fixture into a violation:
 *
 * - lines 3-11, `$with = []` — an empty short array. The compliant form, with
 *   a relationship method present so the fixture is a real model rather than
 *   an empty shell.
 * - lines 13-16, `$with = array()` — the empty long syntax.
 * - lines 18-23, an array holding nothing but a comment. The element search
 *   steps over comments, so this is empty too.
 * - lines 25-28, `$appends = ['slug']` — a populated array on a differently
 *   named property. Only $with drives eager loading.
 * - lines 30-33, `$With = ['parent']` — PHP property names are
 *   case-sensitive and Eloquent reads $with, so this is an unrelated property.
 * - lines 35-38, `$with = self::DEFAULT_RELATIONS` — a non-array default. What
 *   the constant holds is not single-file token content.
 * - lines 40-50, `protected $with;` with no default at all, plus a local
 *   `$with = ['owner']` inside a method. A local variable is not a property,
 *   so the sniff only counts declarations made directly in the class body.
 * - lines 52-60, a nested anonymous class declaring its own populated $with.
 *   Same rule from the other side: the property belongs to the inner class,
 *   not the model being processed. The sniff registers T_CLASS, and PHPCS
 *   gives an anonymous class its own T_ANON_CLASS token, so the inner class is
 *   never a subject in its own right either — a documented boundary, not an
 *   endorsement of the construct.
 * - lines 62-65, a populated $with on a class with no parent at all. $with is
 *   an ordinary property name and this class is not a model.
 * - lines 67-70, a populated $with on `extends Command` — a parent that is not
 *   model-shaped. This is the gate that keeps the sniff off unrelated code.
 * - lines 72-88 and 90-93, four ordinary $with *parameters* carrying a
 *   populated array default, on models that would otherwise be subjects.
 *   PHPCS builds conditions from brace scopes and a function's scope opens at
 *   its `{`, so a parameter sits in the class's scope exactly like a property,
 *   and the conditions check alone cannot tell the two apart. Four shapes,
 *   each differing from the others in one way that matters to the check:
 *
 *   - line 74, a constructor — the shape most easily mistaken for promotion,
 *     and untyped, so nothing separates `$with` from the opening parenthesis.
 *   - line 79, a static finder taking `$with` as its *second* parameter. The
 *     sniff matches a parameter by its token position, so this is what fails
 *     if it ever read the signature's first parameter instead.
 *   - line 84, a query scope, whose first parameter is the query builder.
 *   - line 92, an abstract method. It has no body, so PHPCS records no scope
 *     opener or closer for it at all — the parameter list is all there is.
 *
 *   Dropping the sniff's ParameterDeclaration::isPlainParameter() call reports
 *   all four.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A constructor-promoted $with is a property declaration, so it is reported
 * like the long form: the promoted parameter declares the property and gives
 * it the same default, and it eager loads on every query for the same reason.
 * The AC counts a $with property of any visibility, and promotion is the one
 * spelling that cannot omit the visibility keyword.
 *
 * This is the other side of the parameter exclusion in passing.php, and the
 * reason the sniff excludes a *plain* parameter rather than the parameter list
 * as a whole. Widening that exclusion to every parameter — the obvious fix for
 * the false positive — leaves this fixture silent, so the two files have to be
 * read together.
 *
 * - line 5, `public array $with = ['author']` — the plain promotion.
 * - line 13, `protected readonly array $with = ['post']` — extra modifiers
 *   before the type, which move the variable further from the opening
 *   parenthesis. Reported at its own column, so a check that measured an
 *   offset instead of asking PHPCS for the parameter would miss it.
 * - line 21, `private array $with = []` — promoted but empty. The default
 *   still has to be non-empty, so promotion is not a second way to be flagged;
 *   this is what would fire if promotion bypassed the array check.
 */
it('flags a constructor-promoted $with', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'promoted.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            5 => [EAGER_LOADING_WITH_WARNING],
            13 => [EAGER_LOADING_WITH_WARNING],
        ])
        ->and(array_map('array_keys', $warnings))->toBe([
            5 => [46],
            13 => [58],
        ]);
});

/**
 * Both hops of the declaration walk step over comments as well as whitespace,
 * so a declaration written across them still reads as one. Each hop has its own
 * fixture, because a single file asserting both could not say which hop had
 * stopped working. Whitespace itself is covered by every other fixture here.
 *
 * `spaced-before-assignment.php` puts a block comment between the property
 * name and the `=`: reading the token immediately after the name would land on
 * that comment and dismiss a real declaration.
 */
it('skips comments between the property name and the assignment', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'spaced-before-assignment.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

/**
 * The other hop: a block comment between the `=` and the array literal.
 * Reading the token immediately after the `=` would find the comment instead
 * of the opening bracket and take the default for a non-array.
 */
it('skips comments between the assignment and the array literal', function (): void {
    $warnings = analyzeFixture(EAGER_LOADING_WITH, 'spaced-before-array.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

/**
 * The model-shaped parent names are a public sniff property, so a project
 * whose base model is named something else can point the sniff at it. One
 * fixture pins both directions: `Entry extends Eloquent` is silent under the
 * shipped default — `Eloquent` is neither listed nor `*Model`-suffixed — and
 * reported once the list names it. A property that was ignored would leave
 * both runs identical and fail the second assertion.
 *
 * The configured name is spelled in lower case against a PascalCase parent,
 * because PHP class names are case-insensitive and a consuming ruleset should
 * not have to match the declaration's casing.
 */
it('exposes a configurable model-parent list', function (): void {
    expect(analyzeFixture(EAGER_LOADING_WITH, 'configured.php')->getWarnings())->toBe([]);

    $warnings = analyzeFixture(
        EAGER_LOADING_WITH,
        'configured.php',
        static function (object $sniff): void {
            $sniff->modelParentClasses = ['eloquent'];
        }
    )->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([5 => [EAGER_LOADING_WITH_WARNING]])
        ->and(array_keys($warnings[5]))->toBe([15]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so five half-written shapes have to
 * pass through the sniff without a report and without falling over. None of
 * them eager loads anything, so silence is the correct answer rather than a
 * missed detection — the same treatment the sibling RequireLazyLoadingPrevention
 * sniff gives a truncated call.
 *
 * Each fixture reaches a different guard, which is why they are five files and
 * not one: a malformed statement changes how everything after it in the same
 * file tokenizes, so they cannot be stacked.
 *
 * - `truncated.php` — the class body is never closed, so PHPCS records no
 *   scope_opener and findExtendedClassName() returns false. The parent gate
 *   closes on it before any property is looked at.
 * - `no-default.php` — `protected $with` with no `=` and nothing but the
 *   closing brace after it, so the lookahead for the assignment finds no token
 *   at all. This covers the shape, not the guard that reads as handling it:
 *   the sniff's `$equalPtr === false` test is defensive only, and removing it
 *   leaves this fixture silent all the same, because PHP resolves
 *   `$tokens[false]` to the open tag, which is not a `=`. Said outright here
 *   because no fixture can pin it — mutation-confirmed green.
 * - `no-value.php` — `protected $with =` with nothing after the `=`, so the
 *   lookahead for the default finds no token. That guard *is* load-bearing:
 *   dropping it raises a TypeError on this fixture, since arrayBounds() takes
 *   an int under strict_types.
 * - `unclosed-short-array.php` — `['profile'` with no closer. PHPCS leaves the
 *   bracket as T_OPEN_SQUARE_BRACKET rather than relabelling it
 *   T_OPEN_SHORT_ARRAY, which is why arrayBounds() reads bracket_closer
 *   without a guard; this fixture is what would catch that behaviour changing.
 * - `unclosed-long-array.php` — `array('profile'` with no closer. Here the
 *   T_ARRAY label survives with no parenthesis pair recorded, so the isset()
 *   guard on it is load-bearing and this fixture is its coverage.
 */
it('passes over half-written declarations without falling over', function (string $fixture): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'truncated.php',
    'no-default.php',
    'no-value.php',
    'unclosed-short-array.php',
    'unclosed-long-array.php',
]);

/**
 * Pins the two severity decisions the standard's wording drives: the standard
 * says *avoid*, and a rare legitimate use exists, so violations are warnings
 * rather than errors; and there is no mechanical rewrite, so none is fixable.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(EAGER_LOADING_WITH, 'failing.php');

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
