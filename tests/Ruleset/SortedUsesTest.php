<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const SORTED_USES = 'SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses';
const DISALLOW_GROUP_USE = 'SlevomatCodingStandard.Namespaces.DisallowGroupUse';
const MULTIPLE_USES_PER_LINE = 'SlevomatCodingStandard.Namespaces.MultipleUsesPerLine';

const SORTED_USES_SNIFFS = [SORTED_USES, DISALLOW_GROUP_USE, MULTIPLE_USES_PER_LINE];

$analyzeSortedUses = static fn (string $fixture): LocalFile => analyzeRulesetFixture(
        SORTED_USES_SNIFFS,
        'SortedUses',
        $fixture
    );

it('registers all three sniffs in the master ruleset', function (string $sniff): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey($sniff);
})->with(SORTED_USES_SNIFFS);

it('produces no violations on the compliant fixture', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('leaves a single use statement alone', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('single-use.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags an unsorted block once, at its first out-of-order import', function () use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses('failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 8, 'column' => 1, 'source' => SORTED_USES . '.IncorrectlyOrderedUses'],
    ]);

    expect(violationFixableFlags($file))->toBe([true]);
});

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

it('leaves no violation behind after fixing', function (): void {
    $file = analyzeRulesetFixture(SORTED_USES_SNIFFS, 'SortedUses', 'autofixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('lets the sorting sniff alone be bypassed by group and comma-separated use', function (string $fixture): void {
    $file = analyzeRulesetFixture([SORTED_USES], 'SortedUses', $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['group-use.php', 'multiple-uses-per-line.php']);

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

it('reports the bypass syntaxes as manual fixes, not fixable ones', function (
    string $fixture
) use ($analyzeSortedUses): void {
    $file = $analyzeSortedUses($fixture);
    $before = file_get_contents(fixturePath('_rulesets/SortedUses', $fixture));

    expect(violationFixableFlags($file))->not->toContain(true)
        ->and(autofixedContents($file))->toBe($before);
})->with(['group-use.php', 'multiple-uses-per-line.php']);
