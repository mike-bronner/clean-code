<?php

/**
 * CleanCode.Metrics.NumberOfChildren — PHPMD's Design/NumberOfChildren.
 *
 * Every claim this file makes about PHPMD was measured against a live PHPMD
 * 2.15.0 (PDepend 2.16.2) run, not read off phpmd.org. Three of those
 * measurements contradict a plausible reading of the rule, and each is pinned
 * here:
 *
 *   - The threshold is *inclusive*. PHPMD's rule class reports when
 *     `$nocc >= $threshold`, so a class with exactly `minimum` children is
 *     already reported. #110's acceptance criteria ask for the opposite —
 *     "child count exactly at threshold → no violation" — and the tool this
 *     package replaces does not behave that way. passing.php's fourteen and
 *     failing.php's fifteen are the boundary pair at the shipped default.
 *   - The count is *cross-file*. A live run over a directory reports a parent
 *     for children declared in other files; the same run over the parent's file
 *     alone counts only the children in it. Both halves are pinned below,
 *     because the second is what a single-file `phpcs` invocation does.
 *   - Only `extends` counts, and only directly. Interface implementors,
 *     grandchildren, and anonymous subclasses are all silent — measured, and
 *     each carried by passing.php.
 *
 * docs/phpmd/design-numberofchildren.md records the full mapping.
 */

declare(strict_types=1);

const NUMBER_OF_CHILDREN = 'CleanCode.Metrics.NumberOfChildren';

const NUMBER_OF_CHILDREN_ERROR = 'CleanCode.Metrics.NumberOfChildren.Found';

const NUMBER_OF_CHILDREN_PROJECT = __DIR__ . '/../fixtures/NumberOfChildrenSniff/project';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NUMBER_OF_CHILDREN);
});

it('ships PHPMD\'s own default threshold', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[NUMBER_OF_CHILDREN]];

    expect((int) $sniff->minimum)->toBe(15);
});

/**
 * The violating half of the boundary pair. Fifteen children is the shipped
 * default exactly, and PHPMD reports at `>=`, so this file is a violation while
 * passing.php's fourteen is not.
 *
 * The report lands on the parent's declaration line, not on any child's.
 */
it('reports a parent that has reached the threshold, on its declaration line', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([11 => [NUMBER_OF_CHILDREN_ERROR]]);
});

/**
 * The message quotes the count and the threshold in PHPMD's own wording. A live
 * PHPMD 2.15.0 run emits "The class Base has 3 children. Consider to rebalance
 * this class hierarchy to keep number of children under 3." for a parent of
 * three at a threshold of three; this is the same sentence with this file's
 * numbers.
 */
it('quotes the count and the threshold the way PHPMD does', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'failing.php');
    $messages = violationMessagesByLine($file->getErrors());

    expect($messages[11][0])->toBe(
        'The class Base has 15 children.'
            . ' Consider to rebalance this class hierarchy to keep number of children under 15.'
    );
});

/**
 * The silent half of the boundary pair, and the near-miss shapes with it:
 * fourteen real children, sixteen interface implementors, a grandchild, an
 * interface extending an interface, and sixteen anonymous subclasses. None of
 * them is a direct named child, and a live PHPMD run reports on none of them.
 */
it('stays silent one child below the threshold, and on every near miss', function (): void {
    $file = analyzeFixture(NUMBER_OF_CHILDREN, 'passing.php');

    expect($file->getErrors())->toBe([]);
});

/**
 * One child either side of the shipped default, driven through the property the
 * way a consuming ruleset sets it. Fourteen children reported at `minimum` 14
 * and silent at 15 is the inclusive threshold stated twice — the same fixture
 * flips verdict on the boundary alone, so neither assertion can be satisfied by
 * a sniff that simply never fires or always does.
 */
it('treats the threshold as inclusive', function (): void {
    $reported = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '14');
    $silent = analyzeFixtureWithProperty(NUMBER_OF_CHILDREN, 'passing.php', 'minimum', '15');

    expect(violationSourcesByLine($reported->getErrors()))->toBe([12 => [NUMBER_OF_CHILDREN_ERROR]]);
    expect($silent->getErrors())->toBe([]);
});

/**
 * The rule's whole reason for existing: the parent is reported for children it
 * cannot see from its own file.
 *
 * Fixture\Project\Base has fifteen children across four files and four
 * spellings — five in its own file, four through a plain import, three through
 * an aliased one, and three by fully qualified name. Only the five are visible
 * in Base.php itself, so a sniff that read one file could never reach fifteen.
 */
it('counts children declared in other files of the run', function (): void {
    $file = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_PROJECT,
        NUMBER_OF_CHILDREN_PROJECT . '/Base.php'
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([12 => [NUMBER_OF_CHILDREN_ERROR]]);
    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 15 children');
});

/**
 * Each spelling of the parent's name carries its own share of that fifteen, so
 * dropping any one resolution path drops the count below the threshold and the
 * test above goes silent. This pins which paths those are, by lowering the
 * threshold to each spelling's own contribution and reading the count back.
 *
 * A same-short-name class in another namespace (Fixture\Decoy\Base, three
 * children of its own) is in the same run throughout. It never adds to
 * Fixture\Project\Base's count — which comparing short names rather than fully
 * qualified ones would do, and which would put the count at eighteen.
 */
it('resolves parents fully qualified, keeping a same-named class separate', function (): void {
    $file = analyzeProjectFixture(
        NUMBER_OF_CHILDREN,
        NUMBER_OF_CHILDREN_PROJECT,
        NUMBER_OF_CHILDREN_PROJECT . '/Decoy.php',
        static function (object $sniff): void {
            $sniff->minimum = 3;
        }
    );

    expect(violationMessagesByLine($file->getErrors())[12][0])->toContain('has 3 children');
});

/**
 * The single-file case, which is parity rather than a limitation: point PHPMD
 * at Base.php alone and it counts the five children declared in it, not the
 * fifteen the directory holds. The sniff is handed the same file with no
 * project paths on the config — exactly what `phpcs Base.php` does — and
 * reports the same five.
 *
 * Silent at the shipped default of 15, reported at 5: the pair is what makes
 * this an assertion about the count rather than about the file being skipped.
 */
it('sees only the file in hand when the run has no project paths', function (): void {
    $path = NUMBER_OF_CHILDREN_PROJECT . '/Base.php';
    $default = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path);
    $lowered = analyzeWithSniffs([NUMBER_OF_CHILDREN], $path, static function (object $sniff): void {
        $sniff->minimum = 5;
    });

    expect($default->getErrors())->toBe([]);
    expect(violationMessagesByLine($lowered->getErrors())[12][0])->toContain('has 5 children');
});

/**
 * Piped input has no path, so there is no codebase to resolve children against
 * and nothing the rule can honestly say. The source below would be a violation
 * at this threshold if it were read from a file.
 */
it('says nothing about piped input', function (): void {
    $source = "<?php\nclass Base {}\n"
        . implode("\n", array_map(static fn (int $i): string => "class Child{$i} extends Base {}", range(1, 15)));
    $file = analyzeStdinSource([NUMBER_OF_CHILDREN], $source);

    expect($file->getErrors())->toBe([]);
});
