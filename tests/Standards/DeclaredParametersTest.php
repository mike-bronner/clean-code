<?php

declare(strict_types=1);

const DECLARED_PARAMETERS = 'CleanCode.Methods.DeclaredParameters';

const DECLARED_PARAMETERS_ERROR = DECLARED_PARAMETERS . '.DynamicArguments';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DECLARED_PARAMETERS);
});

it('stays silent on every compliant and near-miss shape', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'passing.php');

    expect($file->getErrors())->toBeEmpty();
    expect($file->getWarnings())->toBeEmpty();
});

it('flags every dynamic-argument read at its own line and column', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 13, 'column' => 13, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 14, 'column' => 21, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 22, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 27, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 34, 'column' => 24, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 40, 'column' => 9, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('resolves use function imports before the global function', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports.php');

    expect(violationTuples($file))->toBe([
        ['line' => 35, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 42, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('binds an aliased import to the local name, not the builtin', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports-aliased.php');

    expect(violationTuples($file))->toBe([
        ['line' => 32, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('classifies each entry of a mixed group import on its own', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports-mixed-group.php');

    expect(violationTuples($file))->toBe([
        ['line' => 31, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 36, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('flags a relative call when the file declares no namespace', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'global-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 26, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 19, 'column' => 26, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('resolves the relative qualifier against the enclosing braced block', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'braced-namespaces.php');

    expect(violationTuples($file))->toBe([
        ['line' => 28, 'column' => 30, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 33, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('applies the exemption to the magic method body only', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'scopes.php');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 21, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 28, 'column' => 32, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 44, 'column' => 12, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 56, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

it('names the offending call as written', function (): void {
    $errors = analyzeFixture(DECLARED_PARAMETERS, 'failing.php')->getErrors();

    expect($errors[13][13][0]['message'])->toBe(
        'func_num_args() is not allowed; declare the parameter list instead of'
            . ' reading arguments dynamically'
    );
    expect($errors[27][16][0]['message'])->toBe(
        'FUNC_GET_ARGS() is not allowed; declare the parameter list instead of'
            . ' reading arguments dynamically'
    );
});

it('reports every violation as non-fixable', function (string $fixture): void {
    $flags = violationFixableFlags(analyzeFixture(DECLARED_PARAMETERS, $fixture));

    expect($flags)->not->toBeEmpty();
    expect($flags)->each->toBeFalse();
})->with([
    'failing.php',
    'scopes.php',
    'imports.php',
    'imports-aliased.php',
    'imports-mixed-group.php',
    'global-namespace.php',
    'braced-namespaces.php',
]);
