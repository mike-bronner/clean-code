<?php

declare(strict_types=1);

const COMBINABLE_CONDITIONS = 'CleanCode.Conditionals.CombinableConditions';

const COMBINABLE_CONDITIONS_CHAIN = COMBINABLE_CONDITIONS . '.ChainBranches';

const COMBINABLE_CONDITIONS_ADJACENT = COMBINABLE_CONDITIONS . '.AdjacentIfs';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMBINABLE_CONDITIONS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns once per participating branch', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 27, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 29, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 42, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 44, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 54, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 56, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 58, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 68, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 72, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 82, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 86, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 94, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 98, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 107, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 111, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 123, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 127, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 136, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 140, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 144, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 155, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 160, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 172, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 174, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 186, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 188, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(26);
});

it('names the other branches of the group and the operator to use', function (): void {
    $messages = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php')->getWarnings();

    expect($messages[27][9][0]['message'])
        ->toContain('the adjacent branch on line 29')
        ->toContain('"||"')
        ->and($messages[56][11][0]['message'])
        ->toContain('the adjacent branches on lines 54 and 58')
        ->and($messages[68][9][0]['message'])
        ->toContain('the adjacent "if" on line 72')
        ->toContain('exiting body')
        ->toContain('"||"')
        ->and($messages[140][9][0]['message'])
        ->toContain('the adjacent "if" statements on lines 136 and 144');
});

it('marks no violation fixable', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'failing.php');

    expect($file->getWarningCount())->toBe(26)
        ->and($file->getFixableCount())->toBe(0);
});

it('warns on every continuation and body shape', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 20, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 22, 'column' => 16, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 31, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 34, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 43, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 45, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 54, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 55, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 62, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 63, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 70, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 72, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 79, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 81, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_ADJACENT],
        ['line' => 91, 'column' => 13, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 93, 'column' => 15, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

it('leaves an else branch and a chain-holding outer branch alone', function (): void {
    $lines = array_keys(analyzeFixture(COMBINABLE_CONDITIONS, 'shapes.php')->getWarnings());

    expect($lines)->not->toContain(46)
        ->and($lines)->not->toContain(89);
});

it('terminates silently on a truncated conditional', function (string $fixture): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, $fixture);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
})->with([
    'truncated-braced.php',
    'truncated-braceless.php',
    'truncated-alternative.php',
]);

it('still reports a comparable pair when the branch after it is truncated', function (): void {
    $file = analyzeFixture(COMBINABLE_CONDITIONS, 'truncated-after-comparable-pair.php');

    expect(warningTuples($file))->toBe([
        ['line' => 24, 'column' => 9, 'source' => COMBINABLE_CONDITIONS_CHAIN],
        ['line' => 26, 'column' => 11, 'source' => COMBINABLE_CONDITIONS_CHAIN],
    ]);
});

it('stays linear on long runs and deep nesting', function (): void {
    $size = 1200;
    $depth = 2400;
    $lines = ['<?php', '', 'final class Scale', '{', '    public function guards(int $code): void', '    {'];
    $runStart = count($lines) + 1;

    for ($index = 0; $index < $size; $index++) {
        $lines[] = '        if ($code === ' . $index . ') {';
        $lines[] = '            return;';
        $lines[] = '        }';
    }

    $lines[] = '    }';
    $lines[] = '';
    $lines[] = '    public function nested(int $code): int';
    $lines[] = '    {';

    for ($level = 0; $level < $depth; $level++) {
        $lines[] = '        if ($code === ' . $level . ')';
    }

    $lines = array_merge($lines, ['        return 0;', '    }', '}', '']);
    $fixture = stageGeneratedFixture('scale.php', implode("\n", $lines));

    $sniff = sniffInstance(COMBINABLE_CONDITIONS);
    $before = $sniff->scanCounts();
    $file = analyzeWithSniffs([COMBINABLE_CONDITIONS], $fixture);
    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    $expected = [];

    for ($index = 0; $index < $size; $index++) {
        $expected[] = [
            'line' => $runStart + ($index * 3),
            'column' => 9,
            'source' => COMBINABLE_CONDITIONS_ADJACENT,
        ];
    }

    expect(warningTuples($file))->toBe($expected)
        ->and($counted['run.memberSkips'])->toBe(
            ($size - 1),
            "the run's other {$size} members are skipped by the walk, not re-measured"
        )
        ->and($counted['braceless.nestingRefusals'])->toBe(
            ($depth - 1),
            "every nested level but the innermost is refused before its end is asked for"
        )
        ->and($counted['braceless.endScans'])->toBe(
            1,
            'only the innermost body, which opens no control structure, is walked to its end'
        );
});

it('leaves the combinable conditional to no other sniff in the ruleset', function (): void {
    $sources = allViolationSourcesByLine(
            analyzeWithMasterRuleset(fixturePath('CombinableConditionsSniff', 'ruleset-overlap.php'))
        );

    expect(array_intersect_key($sources, array_flip([20, 22, 31, 35])))->toBe([
        20 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_CHAIN],
        22 => [
            'CleanCode.Conditionals.AvoidConditionals.ElseIfStatement',
            COMBINABLE_CONDITIONS_CHAIN,
            'CleanCode.Conditionals.DisallowElse.ElseIfFound',
        ],
        31 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_ADJACENT],
        35 => ['CleanCode.Conditionals.AvoidConditionals.IfStatement', COMBINABLE_CONDITIONS_ADJACENT],
    ]);
});

it('registers on the file open tags, so the whole file is walked once', function (): void {
    [, $ruleset] = buildRuleset();
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[COMBINABLE_CONDITIONS]];

    expect($sniff->register())->toBe([T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO]);
});
