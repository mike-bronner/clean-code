<?php

declare(strict_types=1);

use PHP_CodeSniffer\Util\ExitCode;

pest()->group('arch');

const OPERATORS_PASSIVE_SNIFFS = [
    'CleanCode.WhiteSpace.PassiveOperatorSpacing',
    'Generic.WhiteSpace.IncrementDecrementSpacing',
    'Squiz.WhiteSpace.ObjectOperatorSpacing',
    'Squiz.Arrays.ArrayBracketSpacing',
];

it('wires every sniff the standard needs into the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    foreach (OPERATORS_PASSIVE_SNIFFS as $sniffCode) {
        expect($ruleset->sniffCodes)->toHaveKey($sniffCode);
    }
});

it('reports nothing on the compliant fixture', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every passive operator through the sniff that owns it', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'failing.php');
    $sources = violationSourcesByLine($file->getErrors());

    expect(array_keys($sources))->toBe([9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22])
        ->and($sources[9])->toBe(['CleanCode.WhiteSpace.PassiveOperatorSpacing.Identity'])
        ->and($sources[11])->toBe(['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterIncrement'])
        ->and($sources[18])->toBe(['Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket'])
        ->and($sources[19])->toBe([
            'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
            'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
        ])
        ->and($sources[20])->toBe([
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
        ])
        ->and($sources[21])->toBe([
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
        ]);
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'failing.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/../fixtures/_rulesets/OperatorsPassive/autofixed.php')
    );
});

it('converges under the real phpcbf over the whole master ruleset', function (): void {
    $staged = stageFixtureOutsideTests(
        __DIR__ . '/../fixtures/_rulesets/OperatorsPassive/convergence.php'
    );

    $command = sprintf(
        '%s --standard=%s --no-cache %s',
        escapeshellarg(__DIR__ . '/../../vendor/bin/phpcbf'),
        escapeshellarg('CleanCode'),
        escapeshellarg($staged)
    );

    [, , $status] = runOutsidePackage($command);

    expect($status & ExitCode::FAILED_TO_FIX)->toBe(0);

    $fixed = file_get_contents($staged);

    expect($fixed)->toBe(file_get_contents(
        __DIR__ . '/../fixtures/_rulesets/OperatorsPassive/convergence.fixed.php'
    ));

    [, , $secondStatus] = runOutsidePackage($command);

    expect($secondStatus & (ExitCode::FIXABLE | ExitCode::FAILED_TO_FIX))->toBe(0)
        ->and(file_get_contents($staged))->toBe($fixed);
});
