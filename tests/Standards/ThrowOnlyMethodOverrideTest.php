<?php

declare(strict_types=1);

const REFUSED_BEQUEST = 'CleanCode.Pattern.ThrowOnlyMethodOverride';

const REFUSED_BEQUEST_WARNING = REFUSED_BEQUEST . '.RefusedBequest';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFUSED_BEQUEST);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every method whose whole body is a throw', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            33 => [REFUSED_BEQUEST_WARNING],
            41 => [REFUSED_BEQUEST_WARNING],
            46 => [REFUSED_BEQUEST_WARNING],
            54 => [REFUSED_BEQUEST_WARNING],
            72 => [REFUSED_BEQUEST_WARNING],
            80 => [REFUSED_BEQUEST_WARNING],
            94 => [REFUSED_BEQUEST_WARNING],
            99 => [REFUSED_BEQUEST_WARNING],
            115 => [REFUSED_BEQUEST_WARNING],
        ]);
});

it('reports on the function keyword', function (): void {
    $tuples = warningTuples(analyzeFixture(REFUSED_BEQUEST, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 33, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 41, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 46, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 54, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 72, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 80, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 94, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 99, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 115, 'column' => 20, 'source' => REFUSED_BEQUEST_WARNING],
    ]);
});

it('names the method, the principle, and the remedy', function (): void {
    $warnings = analyzeFixture(REFUSED_BEQUEST, 'failing.php')->getWarnings();

    expect($warnings[33][12][0]['message'])
        ->toContain('read()')
        ->toContain('Liskov Substitution')
        ->toContain('Split the hierarchy')
        ->toContain('segregate the interface');
});

it('reports at warning severity, never as an error', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(9);
});

it('offers no fix', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ThrowOnlyMethodOverrideSniff', 'failing.php')));
});
