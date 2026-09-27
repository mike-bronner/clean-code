<?php

declare(strict_types=1);

const REFLECTION_ACCESS = 'CleanCode.Testing.NoReflectionAccess';

const REFLECTION_ACCESS_WARNING = REFLECTION_ACCESS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFLECTION_ACCESS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [REFLECTION_ACCESS_WARNING],
            4 => [REFLECTION_ACCESS_WARNING],
            5 => [REFLECTION_ACCESS_WARNING],
            6 => [REFLECTION_ACCESS_WARNING],
            7 => [REFLECTION_ACCESS_WARNING],
            8 => [REFLECTION_ACCESS_WARNING],
            9 => [REFLECTION_ACCESS_WARNING],
            10 => [REFLECTION_ACCESS_WARNING],
            11 => [REFLECTION_ACCESS_WARNING],
            12 => [REFLECTION_ACCESS_WARNING],
            13 => [REFLECTION_ACCESS_WARNING],
            14 => [REFLECTION_ACCESS_WARNING],
            15 => [REFLECTION_ACCESS_WARNING],
            16 => [REFLECTION_ACCESS_WARNING],
            17 => [REFLECTION_ACCESS_WARNING],
            23 => [REFLECTION_ACCESS_WARNING],
            24 => [REFLECTION_ACCESS_WARNING],
        ]);
});

it('reports at the offending name rather than the operator', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 3, 'column' => 15, 'source' => REFLECTION_ACCESS_WARNING])
        ->toContain(['line' => 5, 'column' => 18, 'source' => REFLECTION_ACCESS_WARNING])
        ->toContain(['line' => 15, 'column' => 54, 'source' => REFLECTION_ACCESS_WARNING]);
});

it('names the construct and the standard in the warning message', function (): void {
    $warnings = analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings();

    expect($warnings[3][15][0]['message'])
        ->toContain('ReflectionMethod')
        ->toContain('test through the public API')
        ->toContain('docs/standards/testing-guidelines.md')
        ->and($warnings[5][18][0]['message'])->toContain('\\ReflectionMethod')
        ->and($warnings[6][14][0]['message'])->toContain('REFLECTIONPROPERTY')
        ->and($warnings[16][23][0]['message'])->toContain('GETMETHOD');
});

it('exposes configurable class and member lists', function (): void {
    expect(array_keys(analyzeFixture(REFLECTION_ACCESS, 'configured.php')->getWarnings()))
        ->toBe([5, 10]);

    $retunedClasses = analyzeFixture(
        REFLECTION_ACCESS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->reflectionClasses = ['ReflectionClass'];
            $sniff->reflectionMembers = [];
        }
    );

    expect(array_keys($retunedClasses->getWarnings()))->toBe([6]);

    $retunedMembers = analyzeFixture(
        REFLECTION_ACCESS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->reflectionClasses = [];
            $sniff->reflectionMembers = ['getName'];
        }
    );

    expect(array_keys($retunedMembers->getWarnings()))->toBe([11]);
});

it('exposes a configurable test-file pattern that gates the whole rule', function (): void {
    $retuned = analyzeFixture(
        REFLECTION_ACCESS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->testFilePatterns = ['*/production/*'];
        }
    );

    expect($retuned->getWarnings())->toBe([])
        ->and($retuned->getErrors())->toBe([])
        ->and(analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings())->toHaveCount(17);
});

it('never inspects a file outside a test path', function (): void {
    $staged = analyzeWithSniffs(
        [REFLECTION_ACCESS],
        stageFixtureOutsideTests(fixturePath('NoReflectionAccessSniff', 'failing.php'))
    );

    expect($staged->getWarnings())->toBe([])
        ->and($staged->getErrors())->toBe([])
        ->and(analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings())->toHaveCount(17);
});

it('honours per-line phpcs:ignore suppression', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'suppressed.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            15 => [REFLECTION_ACCESS_WARNING],
            16 => [REFLECTION_ACCESS_WARNING],
        ]);
});

it('handles a truncated statement without falling over', function (string $fixture, array $lines): void {
    $file = analyzeFixture(REFLECTION_ACCESS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and(array_keys($file->getWarnings()))->toBe($lines);
})->with([
    ['unterminated-member.php', [6, 7]],
    ['unterminated-new.php', [4, 5]],
]);

it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect($file->getWarningCount())->toBe(17)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
