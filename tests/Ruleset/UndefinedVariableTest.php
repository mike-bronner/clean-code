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

it('registers the CleanCode subclass under the upstream sniff code', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes[VARIABLE_ANALYSIS_SNIFF])
        ->toBe(MikeBronner\CleanCode\Overrides\VariableAnalysis\Sniffs\CodeAnalysis\VariableAnalysisSniff::class)
        ->and(is_subclass_of(
                $ruleset->sniffCodes[VARIABLE_ANALYSIS_SNIFF],
                VariableAnalysis\Sniffs\CodeAnalysis\VariableAnalysisSniff::class
            ))->toBeTrue();
});

it('keeps the failing fixture at the same lines and columns', function (): void {
    expect(violationTuples(analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'failing.php')))->toBe([
        ['line' => 17, 'column' => 16, 'source' => UNDEFINED_VARIABLE],
        ['line' => 22, 'column' => 16, 'source' => UNDEFINED_VARIABLE],
        ['line' => 27, 'column' => 16, 'source' => UNDEFINED_VARIABLE],
        ['line' => 32, 'column' => 15, 'source' => UNDEFINED_UNSET_VARIABLE],
    ]);
});

it('treats a by-reference argument passed by name as a definition', function (string $fixture): void {
    $file = analyzeFixture(VARIABLE_ANALYSIS_SNIFF, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['named-by-reference.php', 'nested-arrow-functions.php']);

it('still reports every variable that nothing defines', function (): void {
    [$file, $diagnostics] = withPhpDiagnostics(
            static fn (): object => analyzeFixture(VARIABLE_ANALYSIS_SNIFF, 'still-undefined.php')
        );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 55, 'source' => UNDEFINED_VARIABLE],
        ['line' => 16, 'column' => 19, 'source' => UNDEFINED_VARIABLE],
        ['line' => 24, 'column' => 29, 'source' => UNDEFINED_VARIABLE],
        ['line' => 31, 'column' => 71, 'source' => UNDEFINED_VARIABLE],
        ['line' => 36, 'column' => 48, 'source' => UNDEFINED_VARIABLE],
        ['line' => 41, 'column' => 54, 'source' => UNDEFINED_VARIABLE],
        ['line' => 43, 'column' => 16, 'source' => UNDEFINED_VARIABLE],
        ['line' => 48, 'column' => 51, 'source' => UNDEFINED_VARIABLE],
        ['line' => 50, 'column' => 16, 'source' => UNDEFINED_VARIABLE],
        ['line' => 55, 'column' => 71, 'source' => UNDEFINED_VARIABLE],
        ['line' => 60, 'column' => 48, 'source' => UNDEFINED_VARIABLE],
    ])
        ->and($diagnostics)->toBe([]);
});

it('reports both cases through the upstream sniff alone', function (string $fixture) use ($analyzeUnconfigured): void {
    expect(allViolationSourcesByLine($analyzeUnconfigured($fixture)))->not->toBe([]);
})->with(['named-by-reference.php', 'nested-arrow-functions.php']);
