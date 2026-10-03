<?php

declare(strict_types=1);

const AVOID_DUPLICATE_CODE_BLOCKS = 'CleanCode.Pattern.AvoidDuplicateCodeBlocks';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_DUPLICATE_CODE_BLOCKS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns at every block of a repeated shape, at its first line', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 31, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 52, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 62, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('names the other block from both sides of a pair', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'failing.php')->getWarnings();

    expect($messages[21][9][0]['message'])
        ->toContain('through line 27, is near-identical to the block starting on line 31')
        ->and($messages[31][9][0]['message'])
        ->toContain('through line 37, is near-identical to the block starting on line 21')
        ->and($messages[52][5][0]['message'])
        ->toContain('through line 59, is near-identical to the block starting on line 62')
        ->and($messages[62][5][0]['message'])
        ->toContain('through line 69, is near-identical to the block starting on line 52');
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

it('compares only blocks at or above the default line threshold', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'boundaries.php');

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 25, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('honours a lowered line threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'boundaries.php',
        static function (object $sniff): void {
            $sniff->minimumLines = '4';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 25, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 38, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 45, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('compares each qualified name by the segments it spells', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'qualified-names.php');

    expect(warningTuples($file))->toBe([
        ['line' => 10, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 28, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('says nothing about a file shorter than one window', function (): void {
    expect(warningTuples(analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'floor.php')))->toBe([]);
});

it('floors the line threshold at one', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'floor.php',
        static function (object $sniff): void {
            $sniff->minimumLines = '0';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 16, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 18, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[18][1][0]['message'])
        ->toContain('through line 18, is near-identical to the block starting on line 16');
});

it('reports more, not less, on a mistyped threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_CODE_BLOCKS,
        'floor.php',
        static function (object $sniff): void {
            $sniff->minimumLines = 'not-a-number';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 16, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 18, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('scans the file once however many open tags it carries', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'multiple-open-tags.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 30, 'column' => 1, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[30][1][0]['message'])
        ->toContain('through line 34, is near-identical to the block starting on line 20');
});

it('does not report a run of similar lines against itself', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'repetition.php');

    expect(warningTuples($file))->toBe([
        ['line' => 42, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 47, 'column' => 9, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($file->getWarnings()[47][9][0]['message'])
        ->toContain('through line 51, is near-identical to the block starting on line 42');
});

it('reports every block of a shape that repeats more than once', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'three-copies.php');

    expect(warningTuples($file))->toBe([
        ['line' => 22, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 32, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 42, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ]);
});

it('names every other block of a shape that repeats more than once', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'three-copies.php')->getWarnings();

    expect($messages[22][5][0]['message'])
        ->toContain('through line 29, is near-identical to the blocks starting on lines 32 and 42')
        ->and($messages[32][5][0]['message'])
        ->toContain('through line 39, is near-identical to the blocks starting on lines 22 and 42')
        ->and($messages[42][5][0]['message'])
        ->toContain('through line 49, is near-identical to the blocks starting on lines 22 and 32');
});

it('reports a group through the extent all of its blocks share', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_CODE_BLOCKS, 'shared-extent.php')->getWarnings();

    expect(tuplesFromMessages($messages))->toBe([
        ['line' => 21, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 31, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
        ['line' => 41, 'column' => 5, 'source' => AVOID_DUPLICATE_CODE_BLOCKS . '.Found'],
    ])
        ->and($messages[21][5][0]['message'])->toContain('through line 26, is near-identical')
        ->and($messages[31][5][0]['message'])->toContain('through line 36, is near-identical')
        ->and($messages[41][5][0]['message'])->toContain('through line 46, is near-identical');
});

it('leaves its own source alone', function (): void {
    $file = analyzeWithSniffs(
        [AVOID_DUPLICATE_CODE_BLOCKS],
        cleanCodeRoot() . '/CleanCode/Sniffs/Pattern/AvoidDuplicateCodeBlocksSniff.php'
    );

    expect($file->numTokens)->toBeGreaterThan(0)
        ->and(warningTuples($file))->toBe([]);
});
