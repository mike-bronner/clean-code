<?php

declare(strict_types=1);

const METHOD_NESTING_LEVEL = 'CleanCode.Metrics.MethodNestingLevel';

const METHOD_NESTING_LEVEL_MAX_EXCEEDED = METHOD_NESTING_LEVEL . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(METHOD_NESTING_LEVEL);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each excess control structure at its own line', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 30, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 41, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 56, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 57, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 73, 'column' => 23, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 74, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 91, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 108, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 111, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 123, 'column' => 27, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 140, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 155, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 171, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 186, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 200, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 220, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 239, 'column' => 28, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports the level it measured', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect(reportedNestingLevels($file))->toBe([
        30 => 3,
        41 => 3,
        56 => 3,
        57 => 4,
        73 => 3,
        74 => 4,
        91 => 3,
        108 => 3,
        111 => 4,
        123 => 3,
        140 => 3,
        155 => 3,
        171 => 3,
        186 => 3,
        200 => 3,
        220 => 3,
        239 => 3,
    ]);
});

it('names the level found and the maximum allowed', function (): void {
    $errors = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php')->getErrors();

    expect($errors[30][17][0]['message'])
        ->toBe('Method nesting level (3) exceeds the maximum of 2; refactor to reduce nesting');
});

it('passes at exactly two levels and fails at exactly three', function (): void {
    $atTwo = analyzeFixture(METHOD_NESTING_LEVEL, 'passing.php');
    $atThree = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect($atTwo->getErrors())->toBe([])
        ->and(array_slice(violationTuples($atThree), 0, 1))->toBe([
            ['line' => 30, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ]);
});

it('counts a continuation branch as a level without reporting it twice', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'continuations.php');

    expect(violationTuples($file))->toBe([
        ['line' => 39, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 55, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 71, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 86, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 102, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 122, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 145, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 161, 'column' => 17, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and(reportedNestingLevels($file))->toBe([
        39 => 3,
        55 => 3,
        71 => 3,
        86 => 3,
        102 => 3,
        122 => 3,
        145 => 3,
        161 => 3,
    ]);
});

it('counts an arrow function as a nesting level for its body', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'arrow-functions.php');

    expect(violationTuples($file))->toBe([
        ['line' => 35, 'column' => 23, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 36, 'column' => 21, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 53, 'column' => 29, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 68, 'column' => 18, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 82, 'column' => 28, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
        ['line' => 98, 'column' => 13, 'source' => METHOD_NESTING_LEVEL_MAX_EXCEEDED],
    ])->and(reportedNestingLevels($file))->toBe([
        35 => 3,
        36 => 4,
        53 => 3,
        68 => 3,
        82 => 3,
        98 => 3,
    ]);
});

it('measures a closure the same inside an arrow body as inside a closure body', function (): void {
    $levels = reportedNestingLevels(analyzeFixture(METHOD_NESTING_LEVEL, 'arrow-functions.php'));

    expect($levels[53])->toBe($levels[68]);
});

it('is not already covered by Generic.Metrics.NestingLevel', function (): void {
    $file = analyzeWithStandard('Generic', fixturePath('MethodNestingLevelSniff', 'failing.php'));
    $sources = array_column(violationTuples($file), 'source');

    expect(array_filter($sources, static fn (string $source): bool => str_starts_with(
        $source,
        'Generic.Metrics.NestingLevel'
    )))->toBe([])->and($sources)->not->toBeEmpty();
});

it('is not already covered by SlevomatCodingStandard.Complexity.Cognitive', function (): void {
    $cognitive = analyzeWithStandard(
        'SlevomatCodingStandard',
        fixturePath('MethodNestingLevelSniff', 'failing.php')
    );
    $cognitiveLines = array_column(array_filter(
        violationTuples($cognitive),
        static fn (array $violation): bool => str_starts_with(
            $violation['source'],
            'SlevomatCodingStandard.Complexity.Cognitive'
        )
    ), 'line');

    $ours = array_column(violationTuples(analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php')), 'line');

    expect($cognitiveLines)->toBe([26, 37, 52, 69, 86, 104, 136, 151, 167, 182, 196])
        ->and(array_intersect($cognitiveLines, $ours))->toBe([]);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(METHOD_NESTING_LEVEL, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
