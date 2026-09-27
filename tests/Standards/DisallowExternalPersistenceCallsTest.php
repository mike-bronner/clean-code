<?php

declare(strict_types=1);

const PERSISTENCE = 'CleanCode.Models.DisallowExternalPersistenceCalls';

const PERSISTENCE_WARNING = PERSISTENCE . '.Found';

$stagedRun = static fn (string $fixture, ?callable $configure = null) => analyzeWithSniffs(
    [PERSISTENCE],
    stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', $fixture)),
    $configure
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PERSISTENCE);
});

it('produces no violations on the compliant fixture', function () use ($stagedRun): void {
    $file = $stagedRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line with the expected code', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
            5 => [PERSISTENCE_WARNING],
            6 => [PERSISTENCE_WARNING],
            7 => [PERSISTENCE_WARNING],
            8 => [PERSISTENCE_WARNING],
            9 => [PERSISTENCE_WARNING],
            10 => [PERSISTENCE_WARNING],
            11 => [PERSISTENCE_WARNING],
            12 => [PERSISTENCE_WARNING],
            18 => [PERSISTENCE_WARNING],
            23 => [PERSISTENCE_WARNING],
        ]);
});

it('names the offending method in the warning message', function () use ($stagedRun): void {
    $warnings = $stagedRun('failing.php')->getWarnings();

    expect($warnings[3][8][0]['message'])->toContain('save()')
        ->and($warnings[8][8][0]['message'])->toContain('SAVE()');
});

it('exposes a configurable persistence-method list', function () use ($stagedRun): void {
    expect(array_keys($stagedRun('configured.php')->getWarnings()))->toBe([4]);

    $configured = $stagedRun(
        'configured.php',
        static function (object $sniff): void {
            $sniff->persistenceMethods = ['persist'];
        }
    );

    expect(array_keys($configured->getWarnings()))->toBe([3]);
});

it('is scoped out of test paths', function () use ($stagedRun): void {
    $inRepo = analyzeWithSniffs(
        [PERSISTENCE],
        fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php')
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($stagedRun('failing.php')->getWarnings())->toHaveCount(12);
});

it('handles a truncated chain without falling over', function () use ($stagedRun): void {
    $file = $stagedRun(
        'unterminated.php',
        static function (object $sniff): void {
            $sniff->persistenceMethods = ['save', 'list'];
        }
    );

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
        ]);
});

it('reports detection-only warnings', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getWarningCount())->toBe(12)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php');

    $staged = installedSniffRun(PERSISTENCE, stageFixtureOutsideTests($failing));
    $inRepo = installedSniffRun(PERSISTENCE, $failing);
    $passing = installedSniffRun(
        PERSISTENCE,
        stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', 'passing.php'))
    );

    expect(array_column($staged['messages'], 'source'))->toHaveCount(12)
        ->each->toBe(PERSISTENCE_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
