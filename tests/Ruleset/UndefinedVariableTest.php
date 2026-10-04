<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const VARIABLE_ANALYSIS_SNIFF = 'VariableAnalysis.CodeAnalysis.VariableAnalysis';

const UNDEFINED_VARIABLE = VARIABLE_ANALYSIS_SNIFF . '.UndefinedVariable';

const UNDEFINED_UNSET_VARIABLE = VARIABLE_ANALYSIS_SNIFF . '.UndefinedUnsetVariable';

const UNUSED_VARIABLE_CODE = VARIABLE_ANALYSIS_SNIFF . '.UnusedVariable';

const VARIABLE_ANALYSIS_EXCLUDED_CODES = [
    VARIABLE_ANALYSIS_SNIFF . '.VariableRedeclaration',
    VARIABLE_ANALYSIS_SNIFF . '.SelfOutsideClass',
    VARIABLE_ANALYSIS_SNIFF . '.StaticOutsideClass',
];

$analyzeUnconfigured = static function (string $fixture): LocalFile {
    $config = new ConfigDouble();
    $config->cache = false;
    $config->standards = ['VariableAnalysis'];

    $config->setConfigData(
            'installed_paths',
            cleanCodeRoot() . '/vendor/sirbrillig/phpcs-variable-analysis',
            true
        );

    $file = new LocalFile(
            fixturePath('VariableAnalysisSniff', $fixture),
            new Ruleset($config),
            $config
        );
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(VARIABLE_ANALYSIS_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each undefined read at its own line', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        17 => [UNDEFINED_VARIABLE],
        22 => [UNDEFINED_VARIABLE],
        27 => [UNDEFINED_VARIABLE],
        32 => [UNDEFINED_UNSET_VARIABLE],
    ]);
});

it('reports undefined reads as errors rather than warnings', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

it('flags the divergences from PHPMD exactly where they are recorded', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        34 => [UNDEFINED_VARIABLE],
        52 => [UNUSED_VARIABLE_CODE],
        56 => [UNDEFINED_VARIABLE],
    ]);
});

it('stays blind to the conditional assignment on the sniffs own account', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('divergences.php');

    $flaggedLines = array_keys(allViolationSourcesByLine($file));

    expect($flaggedLines)->toContain(34)
        ->and($flaggedLines)->toContain(56)
        ->and($flaggedLines)->not->toContain(75);
});

it('reports undefined reads without offering an auto-fix', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe([false, false, false, false]);
});

it('leaves the failing fixture byte-identical when the fixer runs', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(4);

    $fixed = autofixedContents($file);

    expect($fixed)->toBe(file_get_contents(fixturePath('VariableAnalysisSniff', 'failing.php')))
        ->and($file->getErrorCount())->toBe(4);
});

it('keeps the excluded codes silent through the master ruleset', function (): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'excluded-codes.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('raises every excluded code without the master rulesets excludes', function () use ($analyzeUnconfigured): void {
    $file = $analyzeUnconfigured('excluded-codes.php');

    $raised = array_merge(...array_values(allViolationSourcesByLine($file)));

    foreach (VARIABLE_ANALYSIS_EXCLUDED_CODES as $code) {
        expect($raised)->toContain($code);
    }
});
