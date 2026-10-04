<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const CONSTRUCTOR_NAME_SNIFF = 'Generic.NamingConventions.ConstructorName';

const CONSTRUCTOR_NAME_OLD_STYLE = CONSTRUCTOR_NAME_SNIFF . '.OldStyle';

const CONSTRUCTOR_NAME_OLD_STYLE_CALL = CONSTRUCTOR_NAME_SNIFF . '.OldStyleCall';

$analyzeUnconfigured = static function (string $fixture): LocalFile {
    $config = new ConfigDouble();
    $config->cache = false;
    $config->standards = ['Generic'];
    $config->sniffs = [CONSTRUCTOR_NAME_SNIFF];

    $file = new LocalFile(
            fixturePath('ConstructorNameSniff', $fixture),
            new Ruleset($config),
            $config
        );
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONSTRUCTOR_NAME_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each PHP4 style constructor at its own line', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        18 => [CONSTRUCTOR_NAME_OLD_STYLE],
        25 => [CONSTRUCTOR_NAME_OLD_STYLE],
        37 => [CONSTRUCTOR_NAME_OLD_STYLE],
    ]);
});

it('reports each violation at the function keyword token', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 18, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
        ['line' => 25, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
        ['line' => 37, 'column' => 12, 'source' => CONSTRUCTOR_NAME_OLD_STYLE],
    ]);
});

it('reports PHP4 style constructors as errors without a severity override', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('is stricter than PHPMD on a namespaced class', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'namespaced-divergence.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        25 => [CONSTRUCTOR_NAME_OLD_STYLE],
    ]);
});

it('stays silent on the shapes only PHPMD flags', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'phpmd-only-divergences.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('stays silent on those shapes on the sniffs own account', function () use ($analyzeUnconfigured): void {
    $unconfigured = $analyzeUnconfigured('phpmd-only-divergences.php');

    expect(allViolationSourcesByLine($unconfigured))->toBe([]);

    $control = $analyzeUnconfigured('excluded-codes.php');

    expect(allViolationSourcesByLine($control))->not->toBe([]);
});

it('reports PHP4 style constructors without offering an auto-fix', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false]);
});

it('leaves the failing fixture byte-identical when the fixer runs', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(3);

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('ConstructorNameSniff', 'failing.php')))
        ->and($file->getErrorCount())->toBe(3);
});

it('keeps the excluded call-site code silent through the master ruleset', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_NAME_SNIFF, 'excluded-codes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('raises the excluded call-site code without our exclude', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('excluded-codes.php');

    expect(allViolationSourcesByLine($file))->toBe([
        33 => [CONSTRUCTOR_NAME_OLD_STYLE_CALL],
    ]);
});
