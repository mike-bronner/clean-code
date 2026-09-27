<?php

declare(strict_types=1);

const EXCESSIVE_PARAMETER_LIST = 'CleanCode.Functions.ExcessiveParameterList';

const EXCESSIVE_PARAMETER_LIST_ERROR = EXCESSIVE_PARAMETER_LIST . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_PARAMETER_LIST);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every excessive parameter list in the failing fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 12, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 19, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 26, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 40, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 44, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 48, 'column' => 21, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 51, 'column' => 1, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 53, 'column' => 5, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 58, 'column' => 1, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 74, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 76, 'column' => 9, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

it('names the declaration, the count, and the threshold in the message', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php')->getErrors();

    expect($errors[48][21][0]['message'])
        ->toContain('method draw()')
        ->toContain('11 parameters')
        ->toContain('maximum of 10');
});

it('calls a function nested inside a method a function, not a method', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php')->getErrors();

    expect($errors[76][9][0]['message'])->toContain('function insideMethod()')
        ->and($errors[74][12][0]['message'])->toContain('method build()')
        ->and($errors[53][5][0]['message'])->toContain('function nested()')
        ->and($errors[7][12][0]['message'])->toContain('method format()');
});

it('reports at exactly the default threshold and not one below it', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'boundaries.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

it('honours a minimum configured in ruleset XML, inclusively', function (): void {
    $file = analyzeWithConfiguredRuleset(
        EXCESSIVE_PARAMETER_LIST,
        'boundaries.php',
        ['minimum' => '5']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 15, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 19, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

it('falls back to the default minimum when the property is unusable', function (string $configured): void {
    $file = analyzeWithConfiguredRuleset(
        EXCESSIVE_PARAMETER_LIST,
        'boundaries.php',
        ['minimum' => $configured]
    );

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
})->with(['ten', '0', '-3', '', '7.5']);

it('reports the anonymous-class method PHPMD misses', function (): void {
    $file = analyzeWithSniffs(
        [EXCESSIVE_PARAMETER_LIST],
        fixturePath('ExcessiveParameterListSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe([
        ['line' => 8, 'column' => 16, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

it('reports without offering a fix', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php');

    expect(violationFixableFlags($file))->toBe(array_fill(0, 12, false))
        ->and($file->getFixableCount())->toBe(0);
});
