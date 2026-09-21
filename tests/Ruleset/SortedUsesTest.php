<?php

/**
 * Integration test for the three Slevomat rules the master CleanCode/ruleset.xml wires
 * together to enforce "Use Statements: Sort Alphabetically" (issue #67).
 * Because the standard is carried by several sniffs rather than one, its
 * fixtures live in tests/fixtures/_rulesets/SortedUses/.
 *
 * AlphabeticallySortedUses does the sorting check, but on its own it is
 * defeatable: it abandons a file entirely the moment it meets a group use
 * (`use App\Nested\{Alpha, Beta};`), and it reads only the first type of a
 * comma-separated use (`use App\Zulu, App\Alpha;`). Either syntax lets a
 * plainly unsorted file exit clean. DisallowGroupUse and MultipleUsesPerLine
 * reject those two syntaxes outright, which is what keeps the standard
 * fail-closed. The tests below pin both halves of that arrangement — that the
 * bypasses are real when the sorting sniff runs alone, and that the trio still
 * refuses the file — so a fixture that trips nothing can never be mistaken for
 * a working configuration.
 *
 * The order itself is decided two ways, and both are pinned below: two imports
 * of the same type are compared by name, while two imports of different types
 * are ranked by the PSR-12 group priority — classes, then functions, then
 * constants.
 *
 * Sortedness is a property of the whole `use` block, not of each entry: the
 * sniff reports one diagnostic at the first out-of-order import and the fixer
 * reorders the entire block, after which every later entry is already correct.
 * The line assertions below encode that deliberately.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const SORTED_USES = 'SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses';
const DISALLOW_GROUP_USE = 'SlevomatCodingStandard.Namespaces.DisallowGroupUse';
const MULTIPLE_USES_PER_LINE = 'SlevomatCodingStandard.Namespaces.MultipleUsesPerLine';

const SORTED_USES_SNIFFS = [SORTED_USES, DISALLOW_GROUP_USE, MULTIPLE_USES_PER_LINE];

/**
 * Runs a fixture through the trio as CleanCode/ruleset.xml configures it. A closure rather
 * than a named function so this file declares no symbols beside its constants,
 * matching tests/Ruleset/CasingConventionsRulesetTest.php.
 */
$analyzeSortedUses = static fn (string $fixture): LocalFile => analyzeRulesetFixture(
    SORTED_USES_SNIFFS,
    'SortedUses',
    $fixture
);

it('registers all three sniffs in the master ruleset', function (string $sniff): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey($sniff);
})->with(SORTED_USES_SNIFFS);

/**
 * passing.php is deliberately discriminating: it carries the near-miss shapes
 * the trio must stay silent on — an aliased import placed by its real name
 * rather than its alias, `use function` and `use const` groups following the
 * class imports in PSR-12 order, and a comment- and blank-line-separated block
 * that is still sorted against the imports above it.
 */
it('produces no violations on the compliant fixture', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A lone import has nothing to compare against, so the sniff must stay silent
 * rather than treat the degenerate case as unsorted.
 */
it('leaves a single use statement alone', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('single-use.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * failing.php has two imports out of place (lines 8 and 9). Only the first is
 * reported — one diagnostic per block, at the first wrong entry.
 */
it('flags an unsorted block once, at its first out-of-order import', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 8, 'column' => 1, 'source' => SORTED_USES . '.IncorrectlyOrderedUses'],
    ]);

    expect(violationFixableFlags($file))->toBe([true]);
});

/**
 * The cross-group half of the sort. The sniff compares two imports of the same
 * type by name, which failing.php pins, and two imports of *different* types by
 * a PSR-12 priority table instead — classes, then functions, then constants.
 * Every group inside these two fixtures is already sorted internally, so the
 * priority table is the only thing left that can report either file, and
 * between them they put each adjacent pair of the table on its violating side:
 * classes standing after functions, and functions standing after constants.
 * A regression that flattened the table, or reordered it, leaves both files
 * silent — which passing.php cannot catch, being compliant in both directions.
 */
it('flags a group standing in the wrong PSR-12 position', function (
    string $fixture,
    int $line
) use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses($fixture);

    expect(violationTuples($file))->toBe([
        ['line' => $line, 'column' => 1, 'source' => SORTED_USES . '.IncorrectlyOrderedUses'],
    ]);

    expect(violationFixableFlags($file))->toBe([true]);
})->with([
    ['function-group-first.php', 14],
    ['const-group-before-function-group.php', 18],
]);

/**
 * That same priority table also drives the fixer, so a regression in it
 * misorders the fixed output rather than only mis-reporting. The expected order
 * below is written out by hand on purpose: a broken table is self-consistent,
 * so re-running the sniff over the fixed file would report nothing either way
 * and prove only that the fixer agrees with itself.
 */
it('rewrites a misplaced group into PSR-12 order', function (
    string $fixture,
    array $expected
) use ($analyzeSortedUses): void {
    preg_match_all('/^use .+;$/m', autofixedContents($analyzeSortedUses($fixture)), $matches);

    expect($matches[0])->toBe($expected);
})->with([
    ['function-group-first.php', [
        'use App\Contracts\Notifier;',
        'use App\Support\Clock;',
        'use function array_map;',
        'use function count;',
        'use const PHP_EOL;',
    ]],
    ['const-group-before-function-group.php', [
        'use App\Contracts\Notifier;',
        'use App\Support\Clock;',
        'use function array_map;',
        'use function count;',
        'use const PHP_EOL;',
        'use const SORT_STRING;',
    ]],
]);

it('sorts the whole block when fixed, leaving the rest untouched', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/SortedUses', 'autofixed.php')));
});

/**
 * The fixed output must itself be clean — a fixer that merely moved the first
 * offender would leave the block still unsorted and still reported.
 */
it('leaves no violation behind after fixing', function (): void {
    $file = analyzeRulesetFixture(SORTED_USES_SNIFFS, 'SortedUses', 'autofixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The fail-closed half. Both bypass fixtures are visibly unsorted, and both
 * make the sorting sniff go silent on its own — so without the companions
 * these files would exit clean.
 */
it('lets the sorting sniff alone be bypassed by group and comma-separated use', function (string $fixture): void {
    $file = analyzeRulesetFixture([SORTED_USES], 'SortedUses', $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['group-use.php', 'multiple-uses-per-line.php']);

/**
 * ...and the silence above has to be a real bypass, not an empty fixture. Strip
 * only the bypass syntax out of each fixture and the sorting sniff immediately
 * reports the imports that are left, which is what proves those imports were
 * unsorted all along. Without this, sanding a bypass fixture down to sorted
 * imports would quietly turn the test above into an assertion about nothing.
 */
it('reports the bypass fixtures once their bypass syntax is removed', function (
    string $fixture,
    string $search,
    string $replace
): void {
    $staged = stageFixtureOutsideTests(fixturePath('_rulesets/SortedUses', $fixture));
    $stripped = str_replace($search, $replace, file_get_contents($staged));

    expect($stripped)->not->toBe(file_get_contents($staged), 'the bypass syntax was not found to strip');

    file_put_contents($staged, $stripped);

    $sources = violationSourcesByLine(analyzeWithSniffs([SORTED_USES], $staged)->getErrors());

    expect(array_merge(...array_values($sources) ?: [[]]))
        ->toBe([SORTED_USES . '.IncorrectlyOrderedUses']);
})->with([
    ['group-use.php', "use App\\Nested\\{Alpha, Beta};\n", ''],
    ['multiple-uses-per-line.php', 'use App\Zulu, App\Alpha;', "use App\\Zulu;\nuse App\\Alpha;"],
]);

it('still refuses a group use, so the bypass cannot ship clean', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('group-use.php');

    expect(violationTuples($file))->toBe([
        ['line' => 10, 'column' => 16, 'source' => DISALLOW_GROUP_USE . '.DisallowedGroupUse'],
        ['line' => 10, 'column' => 22, 'source' => MULTIPLE_USES_PER_LINE . '.MultipleUsesPerLine'],
    ]);
});

it('still refuses a comma-separated use, so the bypass cannot ship clean', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('multiple-uses-per-line.php');

    expect(violationTuples($file))->toBe([
        ['line' => 10, 'column' => 13, 'source' => MULTIPLE_USES_PER_LINE . '.MultipleUsesPerLine'],
    ]);
});

/**
 * Neither bypass is auto-fixable: restructuring a group or comma-separated
 * import into single-line imports is a manual edit, and the fixer must not
 * claim otherwise by leaving a fixable flag set. Documented in
 * docs/standards/use-statements-sort-alphabetically.md.
 */
it('reports the bypass syntaxes as manual fixes, not fixable ones', function (
    string $fixture
) use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses($fixture);
    $before = file_get_contents(fixturePath('_rulesets/SortedUses', $fixture));

    expect(violationFixableFlags($file))->not->toContain(true)
        ->and(autofixedContents($file))->toBe($before);
})->with(['group-use.php', 'multiple-uses-per-line.php']);
