<?php

/**
 * Tests the custom CleanCode.Models.ModelMagicMethodLocation sniff (Models:
 * Structure (Attributes/Queries traits), #39/#192). Fixtures live in
 * tests/fixtures/ModelMagicMethodLocationSniff/.
 *
 * The sniff answers one question per method declaration: is this one of
 * Laravel's four model-magic shapes, and is it written in a class body rather
 * than in the trait the standard asks for? Both halves have to hold, so the
 * silence assertions below each name which half they withdraw — a fixture that
 * simply contains nothing the sniff looks at would pass against a sniff that
 * never fires at all.
 *
 * Lines and columns are asserted separately because tests/Helpers.php has no
 * warning-aware tuple helper: violationTuples() reads getErrors() only, so
 * warnings take violationSourcesByLine() for the sources — as the sibling
 * DisallowAlwaysOnEagerLoading and RequireLazyLoadingPrevention tests do —
 * plus the raw column keys.
 *
 * The rule is detection-only: extracting a method to a trait means writing
 * another file, which the fixer cannot do, so there is no autofixed fixture.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const MODEL_MAGIC_METHOD = 'CleanCode.Models.ModelMagicMethodLocation';

const MODEL_MAGIC_ATTRIBUTE = MODEL_MAGIC_METHOD . '.AttributeMethod';

const MODEL_MAGIC_SCOPE = MODEL_MAGIC_METHOD . '.ScopeMethod';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MODEL_MAGIC_METHOD);
});

/**
 * Every flagged shape, one line each, so dropping any single one of them from
 * the sniff reddens this test on that line alone.
 *
 * Attribute methods — reported as .AttributeMethod, destination Attributes:
 *
 * - line 11, `getTitleAttribute` — the legacy accessor name.
 * - line 16, `setTitleAttribute` — the legacy mutator name.
 * - line 21, `author(): Attribute` — the modern cast, imported plainly.
 * - line 26, `publisher(): CastAttribute` — the same cast under an alias, and
 *   the alias sits behind a comma in a two-name `use` statement, so the
 *   statement reader has to carry its cursor past the `as` clause to reach it.
 * - line 31, `isbn(): ?\Illuminate\…\Attribute` — fully qualified and
 *   nullable, resolved without any import at all.
 * - line 36, `edition(): Attribute|null` — a union type, matched on one member.
 * - line 41, `summary(): (\Countable&\Stringable)|Attribute` — a DNF type, so
 *   both the union and the intersection operator have to split.
 * - line 46, `cover(): GroupedAttribute` — the cast imported through the group
 *   form `use A\{B as C, D};`, which no other line exercises.
 * - line 78, `getBlurbAttribute` — an abstract declaration in a second class,
 *   which also proves the walk restarts for every class in the file.
 *
 * Query scopes — reported as .ScopeMethod, destination Queries:
 *
 * - line 51, `scopePublished` — the local-scope name.
 * - line 57, `draft` — named nothing in particular, carrying `#[Scope]`.
 * - line 64, `archived` — `#[Scope]` behind a second attribute group, so the
 *   walk has to step over `#[Deprecated]` and keep going.
 * - line 70, `featured` — `#[Scope]` as the second member of one group, past
 *   `public static`, which the walk has to skip to reach the group at all.
 * - line 80, `scopeRecent` — an abstract scope in the second class.
 */
it('flags every model-magic method declared in a class body', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php');
    $warnings = $file->getWarnings();
    ksort($warnings);

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([
            11 => [MODEL_MAGIC_ATTRIBUTE],
            16 => [MODEL_MAGIC_ATTRIBUTE],
            21 => [MODEL_MAGIC_ATTRIBUTE],
            26 => [MODEL_MAGIC_ATTRIBUTE],
            31 => [MODEL_MAGIC_ATTRIBUTE],
            36 => [MODEL_MAGIC_ATTRIBUTE],
            41 => [MODEL_MAGIC_ATTRIBUTE],
            46 => [MODEL_MAGIC_ATTRIBUTE],
            51 => [MODEL_MAGIC_SCOPE],
            57 => [MODEL_MAGIC_SCOPE],
            64 => [MODEL_MAGIC_SCOPE],
            70 => [MODEL_MAGIC_SCOPE],
            78 => [MODEL_MAGIC_ATTRIBUTE],
            80 => [MODEL_MAGIC_SCOPE],
        ]);
});

/**
 * The warning lands on the `function` keyword, so the column tracks the
 * modifiers in front of it: 12 for `public function`, 15 for `protected
 * function`, 19 for `public static function`, 21 for `abstract public
 * function`. Pinning them is what would catch the report moving to the class
 * declaration or to the method name.
 */
it('reports each method on its function keyword', function (): void {
    $warnings = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php')->getWarnings();
    ksort($warnings);

    expect(array_map('array_keys', $warnings))->toBe([
        11 => [12],
        16 => [12],
        21 => [12],
        26 => [12],
        31 => [12],
        36 => [12],
        41 => [12],
        46 => [12],
        51 => [12],
        57 => [12],
        64 => [15],
        70 => [19],
        78 => [21],
        80 => [21],
    ]);
});

/**
 * The message names the method and the trait it belongs in, so a reader knows
 * where to move it without opening the standard. The two destinations are what
 * the two violation codes distinguish, and only the message carries the
 * example namespace, so it is asserted rather than assumed.
 */
it('names the method and its destination trait', function (): void {
    $warnings = analyzeFixture(MODEL_MAGIC_METHOD, 'failing.php')->getWarnings();

    expect($warnings[11][12][0]['message'])
        ->toBe(
            'Model method getTitleAttribute() is declared in the class body; extract it to '
                . "the model's Attributes trait (e.g. App\Concerns\Attributes\Book) so the model "
                . 'stays lean (see docs/standards/models-structure-attributes-queries-traits.md)'
        )
        ->and($warnings[51][12][0]['message'])
        ->toBe(
            'Model method scopePublished() is declared in the class body; extract it to '
                . "the model's Queries trait (e.g. App\Concerns\Queries\Book) so the model "
                . 'stays lean (see docs/standards/models-structure-attributes-queries-traits.md)'
        );
});

/**
 * The compliant and near-miss file. It holds the same four shapes the failing
 * fixture is flagged for, each withdrawn by exactly one half of the rule:
 *
 * - A `trait` holding all four — the standard's own destination.
 * - An `interface` and an `enum` declaring them — neither is a class body a
 *   model has, and neither is a token PHP_CodeSniffer labels T_CLASS.
 * - An anonymous class and a named function, both written inside a method
 *   body, so their innermost enclosing scope is not the class.
 * - A closure and an arrow function returning `Attribute`, which are T_CLOSURE
 *   and T_FN and so never reach the walk.
 * - Eloquent's own `getAttribute()`, `setAttribute()` and `getAttributes()`
 *   overrides, plus `scope()`, `scopes()` and `scoped()` — the near misses the
 *   upper-case requirement in each name pattern exists to exclude.
 * - `getdescriptionattribute()`, off-convention lower case. Disclosed false
 *   negative: PHP would resolve it, but matching it would take `scopes()` and
 *   `getAttributes()` with it.
 * - `#[Column(type: 'string', default: Scope::class)]`, where `Scope` is an
 *   argument to another attribute rather than an attribute on the method.
 *
 * The file imports the real cast, so the trait, interface and enum shapes are
 * silent because of where they are declared and not because their return type
 * failed to resolve.
 */
it('leaves compliant and near-miss declarations alone', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A return type of `Attribute` is only Laravel's cast when the file says so.
 * This fixture spells the name three ways and none of them resolves: a bare
 * `Attribute` (this namespace's own class, since nothing imports the cast), an
 * import of a different class aliased to `LocalAttribute`, and that class
 * fully qualified. Without it, a sniff that matched the short name alone would
 * pass every other fixture here.
 */
it('ignores a return type that does not resolve to the cast', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'unimported-attribute.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A class the tokenizer never closed records neither scope_opener nor
 * scope_closer — PHP_CodeSniffer sets the pair together or not at all. The
 * walk needs a closer to stop at, so the sniff passes over the class rather
 * than running to the end of the file on a null bound, which is what it does
 * without the guard.
 */
it('passes over a class the tokenizer never closed', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'unclosed-class.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * `public function ;` has no parameter list, and getDeclarationName() bounds
 * its search at the parenthesis opener — with none to stop at it searches to
 * the end of the file and returns the next declaration's name. Only the
 * accessor on line 18 is reported; without the guard the same name is also
 * reported against line 16.
 */
it('does not borrow a later declaration name for an unfinished one', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'truncated-declaration.php');
    $warnings = $file->getWarnings();

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([18 => [MODEL_MAGIC_ATTRIBUTE]]);
});

/**
 * The import map is memoised, because resolving a return type otherwise
 * rescans the whole file once per method — quadratic on the many-accessor
 * class this sniff exists to find. Memoisation on a sniff instance that
 * outlives the file is what makes a stale map possible, and every other test
 * here builds its own ruleset, so none of them would see one.
 *
 * These two fixtures disagree about what `Attribute` means: failing.php
 * imports Laravel's cast, unimported-attribute.php imports a different class
 * of that short name. Run through one ruleset — one sniff instance — in that
 * order, the second file must still be judged on its own imports.
 */
it('rebuilds its import map for each file in a run', function (): void {
    [$config, $ruleset] = buildRuleset([MODEL_MAGIC_METHOD], true);
    $ruleset->sniffs = array_intersect_key(
        $ruleset->sniffs,
        array_flip($ruleset->sniffCodes === [] ? [] : [$ruleset->sniffCodes[MODEL_MAGIC_METHOD]])
    );

    $directory = __DIR__ . '/../fixtures/ModelMagicMethodLocationSniff/';
    $counts = [];

    foreach (['failing.php', 'unimported-attribute.php'] as $fixture) {
        $file = new LocalFile($directory . $fixture, $ruleset, $config);
        $file->process();
        $counts[$fixture] = $file->getWarningCount();
    }

    expect($counts)->toBe(['failing.php' => 14, 'unimported-attribute.php' => 0]);
});
