<?php

declare(strict_types=1);

const AVOID_DUPLICATE_CODE_BLOCKS = 'CleanCode.Pattern.AvoidDuplicateCodeBlocks';

$withThresholds = static fn (array $properties): callable => static function (object $sniff) use (
    $properties
): void {
    foreach ($properties as $name => $value) {
        $sniff->{$name} = $value;
    }
};

$withoutTokenMinimum = $withThresholds(['minimumTokens' => 0]);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_DUPLICATE_CODE_BLOCKS);
});

it('ships a five-line and a seventy-token minimum', function (): void {
    $sniff = sniffInstance(AVOID_DUPLICATE_CODE_BLOCKS);

    expect($sniff->minimumLines)->toBe(5)
        ->and($sniff->minimumTokens)->toBe(70);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('compares literals, method names and class names as written', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'passing.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 79, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 89, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('warns at every block that repeats another apart from variable names', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 22, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 36, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 62, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 74, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('names the other block from both sides of a pair', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php')->getWarnings();

    expect($messages[22][9][0]['message'])
        ->toContain('through line 34, repeats the block starting on line 36 token for token')
        ->and($messages[36][9][0]['message'])
        ->toContain('through line 48, repeats the block starting on line 22 token for token')
        ->and($messages[62][5][0]['message'])
        ->toContain('through line 71, repeats the block starting on line 74 token for token')
        ->and($messages[74][5][0]['message'])
        ->toContain('through line 83, repeats the block starting on line 62 token for token');
});

it('states the matching rule in the message', function (): void {
    $message = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php')->getWarnings()[22][9][0]['message'];

    expect($message)->toContain('token for token, apart from variable names.')
        ->not->toContain('literal')
        ->not->toContain('identifier');
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

it('sets aside variable names inside a double-quoted string', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'interpolated-strings.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 30, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('leaves a block under the default token minimum alone', function (): void {
    expect(warningTuples(analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'token-minimum.php')))->toBe([]);
});

it('reports a block that holds exactly the token minimum', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'token-minimum.php',
            $withThresholds(['minimumTokens' => 47])
        );

    expect(warningTuples($file))->toBe([
        ['line' => 19, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 30, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('leaves a block one token under the minimum alone', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'token-minimum.php',
            $withThresholds(['minimumTokens' => 48])
        );

    expect(warningTuples($file))->toBe([]);
});

it('reports more, not less, on a mistyped token minimum', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'token-minimum.php',
            $withThresholds(['minimumTokens' => 'not-a-number'])
        );

    expect(warningTuples($file))->toBe([
        ['line' => 19, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 30, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('takes both minimums from a consuming ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'boundaries.php',
            ['minimumLines' => '4', 'minimumTokens' => '23']
        );

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 40, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 46, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('applies a token minimum set by a consuming ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'boundaries.php',
            ['minimumLines' => '4', 'minimumTokens' => '24']
        );

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('compares only blocks at or above the default line threshold', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'boundaries.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('honours a lowered line threshold', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'boundaries.php',
            $withThresholds(['minimumLines' => '4', 'minimumTokens' => 0])
        );

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 28, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 40, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 46, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('compares each qualified name as written', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'qualified-names.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 29, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('says nothing about a file shorter than one window', function () use ($withoutTokenMinimum): void {
    expect(warningTuples(analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'floor.php', $withoutTokenMinimum)))
        ->toBe([]);
});

it('floors the line threshold at one', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'floor.php',
            $withThresholds(['minimumLines' => '0', 'minimumTokens' => 0])
        );

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 19, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[19][1][0]['message'])
        ->toContain('through line 19, repeats the block starting on line 17 token for token');
});

it('reports more, not less, on a mistyped line threshold', function () use ($withThresholds): void {
    $file = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'floor.php',
            $withThresholds(['minimumLines' => 'not-a-number', 'minimumTokens' => 0])
        );

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 19, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('scans the file once however many open tags it carries', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'multiple-open-tags.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 30, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[30][1][0]['message'])
        ->toContain('through line 34, repeats the block starting on line 20 token for token');
});

it('does not report a run of similar lines against itself', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'repetition.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 42, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 47, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[47][9][0]['message'])
        ->toContain('through line 51, repeats the block starting on line 42 token for token');
});

it('reports every block that repeats more than once', function () use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'three-copies.php', $withoutTokenMinimum);

    expect(warningTuples($file))->toBe([
        ['line' => 24, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 34, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 44, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('names every other block that repeats more than once', function () use ($withoutTokenMinimum): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'three-copies.php', $withoutTokenMinimum)
        ->getWarnings();

    expect($messages[24][5][0]['message'])
        ->toContain('through line 31, repeats the blocks starting on lines 34 and 44 token for token')
        ->and($messages[34][5][0]['message'])
        ->toContain('through line 41, repeats the blocks starting on lines 24 and 44 token for token')
        ->and($messages[44][5][0]['message'])
        ->toContain('through line 51, repeats the blocks starting on lines 24 and 34 token for token');
});

it('reports a group through the extent all of its blocks share', function () use ($withoutTokenMinimum): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'shared-extent.php', $withoutTokenMinimum)
        ->getWarnings();

    expect(tuplesFromMessages($messages))->toBe([
        ['line' => 23, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 34, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 45, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($messages[23][5][0]['message'])->toContain('through line 28, repeats')
        ->and($messages[34][5][0]['message'])->toContain('through line 39, repeats')
        ->and($messages[45][5][0]['message'])->toContain('through line 50, repeats');
});

it('leaves its own source alone', function (): void {
    $file = analyzeWithSniffs(
            [AVOID_DUPLICATE_CODE_BLOCKS],
            cleanCodeRoot() . '/CleanCode/Sniffs/Pattern/AvoidDuplicateCodeBlocksSniff.php'
        );

    expect($file->numTokens)->toBeGreaterThan(0)
        ->and(warningTuples($file))->toBe([]);
});

it('reports an identical data-only run at the default thresholds', function (
    string $fixture,
    array $expected
): void {
    expect(warningTuples(analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, $fixture)))->toBe($expected);
})->with([
    'a key list' => [
        'literal-arrays.php',
        [
            ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
            ['line' => 31, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ],
    ],
    'a literal-only call chain' => [
        'literal-call-chain.php',
        [
            ['line' => 19, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
            ['line' => 34, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ],
    ],
    'relation key lists, at exactly the token minimum' => [
        'relation-keys.php',
        [
            ['line' => 20, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
            ['line' => 77, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ],
    ],
]);

it('reports an identical heredoc run that holds the token minimum', function () use ($withThresholds): void {
    $atCount = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'heredoc.php',
            $withThresholds(['minimumTokens' => 19])
        );
    $overCount = analyzeFixture(
            AVOID_DUPLICATE_CODE_BLOCKS,
            'heredoc.php',
            $withThresholds(['minimumTokens' => 20])
        );

    expect(warningTuples($atCount))->toBe([
        ['line' => 23, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 37, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and(warningTuples($overCount))->toBe([]);
});

it('leaves a data run that differs in one literal alone', function (
    string $fixture,
    array $identicalPair
) use ($withoutTokenMinimum): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, $fixture, $withoutTokenMinimum);

    expect(array_column(warningTuples($file), 'line'))->toBe($identicalPair);
})->with([
    'a key list' => ['literal-arrays.php', [21, 31]],
    'a literal-only call chain' => ['literal-call-chain.php', [19, 34]],
    'a heredoc' => ['heredoc.php', [23, 37]],
    'literal entries between logic' => ['mixed-logic.php', []],
]);
