<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const PASSIVE_OPERATOR_SPACING = 'CleanCode.WhiteSpace.PassiveOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PASSIVE_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 10, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 11, 'column' => 19, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 12, 'column' => 17, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 13, 'column' => 21, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 13, 'column' => 31, 'source' => PASSIVE_OPERATOR_SPACING . '.Execution'],
        ['line' => 14, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 15, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 24, 'column' => 27, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 24, 'column' => 29, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 25, 'column' => 27, 'source' => PASSIVE_OPERATOR_SPACING . '.ErrorControl'],
        ['line' => 25, 'column' => 29, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity'],
        ['line' => 35, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
        ['line' => 38, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(14)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php');

    expect(autofixedContents($file))->toBe(
            file_get_contents(__DIR__ . '/../fixtures/PassiveOperatorSpacingSniff/autofixed.php')
        );
});

it('owns a sign that opens a statement or a PHP block', function (): void {
    $tuples = violationTuples(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php'));

    $atLine = static fn (int $line): array => array_values(array_filter(
            $tuples,
            static fn (array $violation): bool => $violation['line'] === $line
        ));

    expect($atLine(35))
        ->toBe([['line' => 35, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']])
        ->and($atLine(38))
        ->toBe([['line' => 38, 'column' => 5, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']]);
});

it('leaves a same-direction sign pair untouched', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'guarded.php');
    $path = __DIR__ . '/../fixtures/PassiveOperatorSpacingSniff/guarded.php';

    expect($file->getErrorCount())->toBe(0)
        ->and(autofixedContents($file))->toBe(file_get_contents($path));
});

it('still reports a cross-direction sign pair', function (): void {
    $tuples = violationTuples(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php'));

    $atLine = static fn (int $line): array => array_values(array_filter(
            $tuples,
            static fn (array $violation): bool => $violation['line'] === $line
        ));

    expect($atLine(14))
        ->toBe([['line' => 14, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Negation']])
        ->and($atLine(15))
        ->toBe([['line' => 15, 'column' => 24, 'source' => PASSIVE_OPERATOR_SPACING . '.Identity']]);
});

it('never reports a binary sign', function (): void {
    $file = analyzeFixture(PASSIVE_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrorCount())->toBe(0);
});

it('leaves backtick content alone when it cannot be trimmed', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
                'preg_replace',
                static fn (): array => violationSourcesByLine(
                        analyzeFixture(PASSIVE_OPERATOR_SPACING, 'failing.php')->getErrors()
                    ),
                static fn (string $pattern): bool => str_contains($pattern, '[ \t]+')
            );
    });

    expect(array_keys($expected))->toContain(12)
        ->and(array_keys($degraded))->not->toContain(12)
        ->and($diagnostics)->toBe([]);
});
