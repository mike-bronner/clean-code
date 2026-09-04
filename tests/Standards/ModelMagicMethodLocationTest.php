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
 * A class the tokenizer never closed opens no scope, so PHP_CodeSniffer records
 * no enclosing condition on the declarations written inside it. isOwnMethod()
 * asks for the nearest enclosing class and gets none, so the accessor is not a
 * member of anything and is not reported.
 *
 * Non-vacuous: making isOwnMethod() return true unconditionally reports the
 * accessor on line 14 and reddens this test.
 */
it('passes over a class the tokenizer never closed', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'unclosed-class.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * declarationName() reads the name from the tokens and requires two things of
 * it: that the token after `function` is a name at all, and that the parameter
 * list's opening parenthesis follows it. The fixture cuts a declaration short
 * in both places, because each test catches a shape the other lets through.
 *
 * - line 21, `public function ;` — no name. The name test rejects it first.
 * - line 29, `public function getSubtitleAttribute` — a name spelled as an
 *   accessor, no parameter list. Only the parenthesis test rejects it.
 *
 * Requiring the parenthesis is also what keeps some *later* declaration's name
 * off an unfinished line: getDeclarationName() bounds its search at an opener
 * that is not there and walks on to the next one.
 *
 * The parenthesis test is the one this fixture discriminates, and line 28 is
 * here because nothing else reached it: deleting that test reports line 29 and
 * reddens this assertion. Deleting the *name* test changes nothing, because
 * the token after line 21's `function` is a semicolon and the parenthesis test
 * rejects it a step later — it is kept as the type guard that makes reading the
 * name well-defined, not as a check the fixtures pin, and this says so rather
 * than implying coverage it does not carry. Only the finished accessor on line
 * 23 is reported.
 */
it('does not borrow a later declaration name for an unfinished one', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'truncated-declaration.php');
    $warnings = $file->getWarnings();

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($warnings))->toBe([23 => [MODEL_MAGIC_ATTRIBUTE]]);
});

/**
 * A sniff object outlives the file it is given: PHP_CodeSniffer builds one
 * instance per ruleset and runs every file through it. The import map is
 * therefore rebuilt per class rather than cached on the instance, and this test
 * is what holds that decision in place — a future memoisation that keys on
 * anything staler than the file would redden it.
 *
 * These two fixtures disagree about what `Attribute` means: failing.php
 * imports Laravel's cast, unimported-attribute.php imports a different class
 * of that short name. Run through one ruleset — one sniff instance — in that
 * order, the second file must still be judged on its own imports.
 */
it('judges each file in a run on its own imports', function (): void {
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

/**
 * A group `use` may mix class, function and constant imports, and the keyword
 * binds to its own member only — `use A\{function b, const C, D};` is one
 * statement importing one class. Only the class member may be recorded.
 *
 * The fixture writes both keyword members ahead of the class member and binds
 * all three to the name `Attribute`, which PHP allows because the three live in
 * separate symbol tables. Members merge first-one-wins, so a reader that takes
 * either keyword member for a class import binds `attribute` to it and
 * discards the real `Casts\Attribute` behind it — and title() on line 26, a
 * method returning exactly that cast, goes unreported.
 *
 * Non-vacuous, and the reason this fixture exists: dropping the keyword test
 * from readMember() drops line 26 from this assertion, leaving it empty.
 * subtitle() on line 33 is the other half — `makeAttribute` is imported as a
 * function, so nothing binds `MakeAttribute` as a class and its return type
 * must stay unresolved rather than being read as the cast.
 */
it('reads a group import past its function and constant members', function (): void {
    $file = analyzeFixture(MODEL_MAGIC_METHOD, 'group-import.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))
        ->toBe([26 => [MODEL_MAGIC_ATTRIBUTE]]);
});

/**
 * The sniff's own source passes rules.xml, the standard it belongs to — the
 * claim rules.xml makes about this file, and the reason the file reads the way
 * it does: match(true) guard chains instead of if
 * (CleanCode.Conditionals.AvoidConditionals), the File API instead of the token
 * array (CleanCode.Arrays.ArrayAccessors), flat const value lists instead of
 * stacked one-per-line tables (CleanCode.Pattern.AvoidDuplicateCodeBlocks) and
 * a NOWDOC message (CleanCode.Strings.MultilineStrings). Registering on T_CLASS
 * and resolving scope through getCondition() rather than the token array's
 * scope_opener/scope_closer pair is part of the same constraint: File publishes
 * no accessor for those bounds.
 *
 * Asserted here because nothing else does, and because a sibling standard
 * landing in rules.xml later can falsify the claim without touching this file
 * — which is exactly what happened to ManualModelResolutionSniff.php, whose
 * own copy of this test exists for that reason. Warnings are counted alongside
 * errors on purpose: this sniff reports warnings itself, so a check that read
 * errors only would stay green through exactly that drift.
 *
 * Every sniff wired into rules.xml is active, not just this one, because the
 * claim is about the whole standard. Run through the *installed* phpcs rather
 * than an in-process ruleset, because "exits 0" is a claim about the binary a
 * consumer runs.
 *
 * The one exception, recorded rather than silenced, and the same one
 * ManualModelResolutionTest.php records, arrived with #159:
 * CleanCode.ClearCode.SectionComment warns on the eight standalone comments in
 * this file that explain *why* rather than labelling a block. The token stream
 * cannot separate the two, which is why that rule is advisory, and rewriting
 * this package's explanatory comments is out of scope for #159 — so the
 * warnings are recorded here like any other sniff's output rather than
 * special-cased away. The assertion stays exact — every source named, nothing
 * else — so it still reddens on any *other* drift, which is the reason it was
 * written.
 */
it('passes the standard it belongs to', function (): void {
    $report = installedPhpcsReport(
        cleanCodeRoot() . '/rules.xml',
        cleanCodeRoot() . '/CleanCode/Sniffs/Models/ModelMagicMethodLocationSniff.php'
    );

    expect(array_column($report, 'source'))->toBe([
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
        'CleanCode.ClearCode.SectionComment.Found',
    ]);
});
