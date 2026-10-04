<?php

declare(strict_types=1);

const OPERATOR_LINE_BREAK = 'CleanCode.Operators.OperatorLineBreak';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(OPERATOR_LINE_BREAK);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every dangling operator at its line', function (): void {
    $tuples = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php'));
    $expected = [[5, 19], [8, 22], [12, 29], [16, 9], [19, 16], [22, 12]];

    expect($tuples)->toHaveCount(count($expected));

    foreach ($expected as $index => [$line, $column]) {
        expect($tuples[$index])->toBe([
            'line' => $line,
            'column' => $column,
            'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd',
        ]);
    }
});

it('defers dangling operators inside conditions', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'deferred-conditional.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports a dangling non-boolean operator in a multi-condition', function (): void {
    $tuples = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'reported-conditional.php'));
    $expected = [[9, 8], [19, 14], [29, 13], [42, 24], [45, 21]];

    expect($tuples)->toHaveCount(count($expected));

    foreach ($expected as $index => [$line, $column]) {
        expect($tuples[$index])->toBe([
            'line' => $line,
            'column' => $column,
            'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd',
        ]);
    }
});

it('reports a dangling boolean outside the clause it defers', function (): void {
    $reported = violationTuples(analyzeFixture(OPERATOR_LINE_BREAK, 'reported-conditional.php'));
    $deferred = analyzeFixture(OPERATOR_LINE_BREAK, 'deferred-conditional.php');

    $inForHeader = array_values(array_filter(
            $reported,
            static fn (array $violation): bool => in_array($violation['line'], [42, 45], true)
        ));

    expect($inForHeader)->toHaveCount(2)
        ->and($inForHeader[0])
        ->toBe(['line' => 42, 'column' => 24, 'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd'])
        ->and($inForHeader[1])
        ->toBe(['line' => 45, 'column' => 21, 'source' => OPERATOR_LINE_BREAK . '.OperatorAtLineEnd'])
        ->and($deferred->getErrors())->toBe([]);

    $source = file(fixturePath(sniffFixtureDirectory(OPERATOR_LINE_BREAK), 'reported-conditional.php'));

    expect(trim($source[41]))->toBe('$ready = $isActive &&')
        ->and(trim($source[44]))->toBe('$ready = $ready ||');
});

it('fixes every violation it reports', function (): void {
    $file = analyzeFixture(OPERATOR_LINE_BREAK, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});
