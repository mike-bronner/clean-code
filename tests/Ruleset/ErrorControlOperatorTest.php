<?php

declare(strict_types=1);

const SILENCED_ERRORS_SNIFF = 'Generic.PHP.NoSilencedErrors';

const SILENCED_ERRORS_CODE = SILENCED_ERRORS_SNIFF . '.Forbidden';

const SILENCED_ERRORS_VIOLATIONS = [
    ['line' => 25, 'column' => 17, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 26, 'column' => 16, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 27, 'column' => 21, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 28, 'column' => 19, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 29, 'column' => 19, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 30, 'column' => 20, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 31, 'column' => 17, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 31, 'column' => 34, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 33, 'column' => 9, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 34, 'column' => 9, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 47, 'column' => 12, 'source' => SILENCED_ERRORS_CODE],
];

const SILENCED_ERRORS_DIVERGENCES = [
    ['line' => 19, 'column' => 13, 'source' => SILENCED_ERRORS_CODE],
    ['line' => 20, 'column' => 8, 'source' => SILENCED_ERRORS_CODE],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SILENCED_ERRORS_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each error-control operator at its own line and column', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe(SILENCED_ERRORS_VIOLATIONS);
});

it('reports suppressed errors as errors rather than warnings', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(SILENCED_ERRORS_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and(warningTuples($file))->toBe([]);
});

it('reports suppressed errors without offering an auto-fix', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(SILENCED_ERRORS_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))
        ->toBe(array_fill(0, count(SILENCED_ERRORS_VIOLATIONS), false));
});

it('flags error-control operators at file scope, where PHPMD stays silent', function (): void {
    $file = analyzeFixture(SILENCED_ERRORS_SNIFF, 'divergences.php');

    expect(violationTuples($file))->toBe(SILENCED_ERRORS_DIVERGENCES);
});
