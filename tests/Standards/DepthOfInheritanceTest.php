<?php

/**
 * CleanCode.Metrics.DepthOfInheritance — PHPMD's Design/DepthOfInheritance.
 *
 * Every claim this file makes about PHPMD was measured against a live PHPMD
 * 2.15.0 (PDepend 2.16.2) run over these same fixtures, not read off
 * phpmd.org. Three of those measurements contradict a plausible reading of the
 * rule, and all three are pinned here:
 *
 *   - The threshold is *inclusive*, and `minimum` is a floor. PHPMD's rule
 *     class falls back from `maximum` to `minimum` and switches from `>` to
 *     `>=` when it does, so a class with exactly `minimum` parents is already
 *     reported. "Maximum number of acceptable parent classes" — the wording on
 *     phpmd.org and in #112's acceptance criteria — describes the opposite.
 *     `boundaries.php` pins 5/6/7 against PHPMD's own output on that file.
 *   - A parent PDepend never saw *declared* weighs **two**, not one, because
 *     InheritanceAnalyzer::calculateDepthOfInheritanceTree() adds an extra
 *     `++$dit` for a parent that is not user-defined. Four in-project
 *     ancestors above an unseen base therefore already reach 6. `failing.php`
 *     carries that shape as `Grafted`.
 *   - Only classes are checked. The rule is ClassAware, and a live run says
 *     nothing about an interface hierarchy however deep, nor about an
 *     anonymous class in its own right. `passing.php` carries both.
 *
 * The cross-file half has no single-file equivalent, so it is asserted through
 * analyzeFileset() over `project/` rather than through analyzeFixture().
 *
 * docs/phpmd/design-depthofinheritance.md records the full mapping and the
 * measured PHPMD output for each fixture.
 */

declare(strict_types=1);

const DEPTH_OF_INHERITANCE = 'CleanCode.Metrics.DepthOfInheritance';

const DEPTH_OF_INHERITANCE_ERROR = 'CleanCode.Metrics.DepthOfInheritance.TooDeep';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DEPTH_OF_INHERITANCE);
});

/**
 * The shipped threshold, read off the instance rules.xml actually configured.
 * PHPMD's own default, spelled with PHPMD's own property name.
 */
it('ships PHPMD\'s default minimum of 6', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[DEPTH_OF_INHERITANCE]];

    expect($sniff->minimum)->toBe(6);
});

/**
 * Compliant code and the near-miss shapes. A five-parent chain sits one below
 * the threshold; the interface hierarchy above it is seven deep and must stay
 * silent, as must the anonymous class extending the deepest class in the file.
 *
 * PHPMD 2.15.0 at its shipped default reports nothing on this fixture.
 */
it('says nothing about a chain below the threshold, or about a non-class', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'passing.php')))->toBe([]);
});

/**
 * The three violating shapes, each reported once on its own declaration line.
 *
 * Line 56 is the `abstract` keyword, not the `class` keyword two lines below
 * it and not the attribute above it: PDepend takes a class's start line from
 * its first modifier. Line 85 carries two declarations and reports twice,
 * which is what pins each report to the right one of them.
 *
 * PHPMD 2.15.0 at its shipped default reports this file at exactly lines 51,
 * 56, 81, 85 and 85.
 */
it('flags a class whose parent count reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'failing.php')))->toBe([
        ['line' => 51, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 56, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 81, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 85, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 85, 'column' => 35, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

/**
 * The count belongs in the message: a metric rule that only says "too deep"
 * makes the reader re-count the chain by hand. Both numbers are quoted.
 *
 * `Grafted` is the discriminating one — it names 6 with only four resolved
 * ancestors, so a sniff that weighed an unseen parent as 1 would either say 5
 * here or stay silent altogether.
 */
it('names the class, its parent count, and the threshold', function (): void {
    $errors = analyzeFixture(DEPTH_OF_INHERITANCE, 'failing.php')->getErrors();

    expect($errors[51][1][0]['message'])->toBe(
        'The class Level6 has 6 parents. Current threshold is 6.'
            . ' Reduce the depth of this class hierarchy.'
    );
    expect($errors[81][1][0]['message'])->toBe(
        'The class Grafted has 6 parents. Current threshold is 6.'
            . ' Reduce the depth of this class hierarchy.'
    );
});

/**
 * The inclusive boundary, in one file: five parents is silent, six is a
 * violation, seven is a violation. A sniff that read `minimum` as a strict
 * "more than" — which is how phpmd.org and #112 both describe it — would
 * report only line 44 and still pass every other assertion here.
 *
 * PHPMD 2.15.0 at its shipped default over this same fixture reports lines 40
 * and 44 and says nothing about the five-parent class on line 36.
 */
it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'boundaries.php')))->toBe([
        ['line' => 40, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
        ['line' => 44, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

/**
 * The threshold is configurable, under PHPMD's own property name.
 *
 * Raised to 7, the six-parent class falls silent and only the seven-parent one
 * is left; lowered to 5, the five-parent class joins them.
 */
it('takes its threshold from the minimum property', function (int $minimum, array $lines): void {
    $file = analyzeFixtureWithProperty(DEPTH_OF_INHERITANCE, 'boundaries.php', 'minimum', $minimum);

    expect(array_keys(violationSourcesByLine($file->getErrors())))->toBe($lines);
})->with([
    'raised to 7' => [7, [44]],
    'shipped 6' => [6, [40, 44]],
    'lowered to 5' => [5, [36, 40, 44]],
]);

/**
 * A cycle has no depth to report. The walk has to notice that it has come back
 * to a class it already counted and abandon the measurement, rather than count
 * round the loop until it passes the threshold — or forever.
 *
 * PHPMD 2.15.0 reports nothing here even with `minimum` lowered to 1, so
 * silence is the faithful answer and not merely the safe one.
 */
it('abandons the count on an inheritance cycle', function (): void {
    $file = analyzeFixtureWithProperty(DEPTH_OF_INHERITANCE, 'cycle.php', 'minimum', 1);

    expect(violationTuples($file))->toBe([]);
});

/**
 * The cross-file half, and the only assertion here that a single LocalFile
 * cannot make.
 *
 * `project/` is one chain of seven classes spread over four files, written so
 * that each link uses a different spelling: a plain `use` import, a group
 * import with an alias, a `namespace\`-relative name, a fully qualified name,
 * and an import inside a braced namespace. Get any one of them wrong and the
 * chain breaks at that link, the parent reads as unseen, and the count
 * collapses — so both reports below depend on all five resolving.
 *
 * PHPMD 2.15.0 over this same directory reports exactly leaf.php:23 (6) and
 * braced.php:14 (7).
 */
it('resolves a parent chain across the files being analysed', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect(array_keys($files))->toBe([
        'base.php',
        'braced.php',
        'interpolated.php',
        'leaf.php',
        'mid.php',
    ]);
    expect(violationTuples($files['base.php']))->toBe([]);
    expect(violationTuples($files['mid.php']))->toBe([]);
    expect(violationTuples($files['leaf.php']))->toBe([
        ['line' => 23, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
    expect(violationTuples($files['braced.php']))->toBe([
        ['line' => 14, 'column' => 5, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

/**
 * `${expr}`, the other spelling PHP opens with a token and closes with a bare
 * brace, across a namespace boundary.
 *
 * `interpolated.php` interpolates in its first braced namespace and imports in
 * its second. Stop counting T_DOLLAR_OPEN_CURLY_BRACES and the first namespace
 * closes early, the import is read as a trait `use` and dropped, and `Deepest`
 * falls to the 2 an unseen parent weighs — silent, where eight parents belong.
 *
 * The count is asserted, not just the line: a broken link that still cleared
 * the threshold would pass on the line alone.
 */
it('counts a ${expr} interpolation opened across a namespace boundary', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect(violationTuples($files['interpolated.php']))->toBe([
        ['line' => 34, 'column' => 5, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
    expect($files['interpolated.php']->getErrors()[34][5][0]['message'])->toBe(
        'The class Deepest has 8 parents. Current threshold is 6.'
            . ' Reduce the depth of this class hierarchy.'
    );
});

/**
 * `{$expr}` in the ordinary shape: one file, one unbraced namespace.
 *
 * The brace this opens is a token; the one that closes it is bare. Miscount it
 * and `Consumer`'s trait `use` reads as an import of the short name `Base5`,
 * which sends `Deep extends Base5` to the trait in `Support` instead of to the
 * six-deep class beside it — and a real violation goes unreported.
 */
it('counts a {$expr} interpolation inside a class body', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'interpolation.php')))->toBe([
        ['line' => 66, 'column' => 1, 'source' => DEPTH_OF_INHERITANCE_ERROR],
    ]);
});

/**
 * The counts the cross-file chain produces, which the line numbers alone do
 * not pin: a broken link that still left the class above the threshold would
 * satisfy the assertion above and fail this one.
 */
it('counts every resolved ancestor across files', function (): void {
    $directory = fixturePath(sniffFixtureDirectory(DEPTH_OF_INHERITANCE), 'project');
    $files = analyzeFileset([DEPTH_OF_INHERITANCE], $directory);

    expect($files['leaf.php']->getErrors()[23][1][0]['message'])->toBe(
        'The class Leaf3 has 6 parents. Current threshold is 6.'
            . ' Reduce the depth of this class hierarchy.'
    );
    expect($files['braced.php']->getErrors()[14][5][0]['message'])->toBe(
        'The class Braced1 has 7 parents. Current threshold is 6.'
            . ' Reduce the depth of this class hierarchy.'
    );
});

/**
 * The limitation the cross-file model carries, stated as behaviour rather than
 * left to the docs: an ancestor outside the analysed set is unseen, and an
 * unseen parent ends the walk at 2.
 *
 * `leaf.php` alone holds three of the seven classes, and its deepest reads 4
 * — two resolved same-file parents plus the unseen one, weighed twice —
 * against the 6 it reads when the whole directory is analysed. PHPMD 2.15.0
 * given only this file is likewise silent.
 */
it('stops at the edge of the analysed set', function (): void {
    expect(violationTuples(analyzeFixture(DEPTH_OF_INHERITANCE, 'project/leaf.php')))->toBe([]);
});
