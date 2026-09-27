<?php

declare(strict_types=1);

const NO_NULL_ARGUMENTS = 'CleanCode.Methods.NoNullArguments';

const NO_NULL_ARGUMENTS_POSITIONAL = NO_NULL_ARGUMENTS . '.PositionalNull';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_NULL_ARGUMENTS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every positional null at its own line', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        23 => [NO_NULL_ARGUMENTS_POSITIONAL],
        30 => [NO_NULL_ARGUMENTS_POSITIONAL, NO_NULL_ARGUMENTS_POSITIONAL],
        69 => [NO_NULL_ARGUMENTS_POSITIONAL],
        70 => [NO_NULL_ARGUMENTS_POSITIONAL],
        73 => [NO_NULL_ARGUMENTS_POSITIONAL],
        74 => [NO_NULL_ARGUMENTS_POSITIONAL],
        77 => [NO_NULL_ARGUMENTS_POSITIONAL],
        80 => [NO_NULL_ARGUMENTS_POSITIONAL],
        81 => [NO_NULL_ARGUMENTS_POSITIONAL],
        84 => [NO_NULL_ARGUMENTS_POSITIONAL, NO_NULL_ARGUMENTS_POSITIONAL],
        88 => [NO_NULL_ARGUMENTS_POSITIONAL],
        93 => [NO_NULL_ARGUMENTS_POSITIONAL],
        96 => [NO_NULL_ARGUMENTS_POSITIONAL],
        100 => [NO_NULL_ARGUMENTS_POSITIONAL],
        132 => [NO_NULL_ARGUMENTS_POSITIONAL],
        135 => [NO_NULL_ARGUMENTS_POSITIONAL],
        138 => [NO_NULL_ARGUMENTS_POSITIONAL],
        142 => [NO_NULL_ARGUMENTS_POSITIONAL],
        146 => [NO_NULL_ARGUMENTS_POSITIONAL],
        149 => [NO_NULL_ARGUMENTS_POSITIONAL],
        150 => [NO_NULL_ARGUMENTS_POSITIONAL],
        155 => [NO_NULL_ARGUMENTS_POSITIONAL],
        156 => [NO_NULL_ARGUMENTS_POSITIONAL],
        196 => [NO_NULL_ARGUMENTS_POSITIONAL],
        213 => [NO_NULL_ARGUMENTS_POSITIONAL],
        214 => [NO_NULL_ARGUMENTS_POSITIONAL],
        215 => [NO_NULL_ARGUMENTS_POSITIONAL],
        222 => [NO_NULL_ARGUMENTS_POSITIONAL],
        223 => [NO_NULL_ARGUMENTS_POSITIONAL],
        251 => [NO_NULL_ARGUMENTS_POSITIONAL],
        270 => [NO_NULL_ARGUMENTS_POSITIONAL],
        287 => [NO_NULL_ARGUMENTS_POSITIONAL],
        302 => [NO_NULL_ARGUMENTS_POSITIONAL],
        311 => [NO_NULL_ARGUMENTS_POSITIONAL],
    ]);
});

it('raises no warnings alongside the errors', function (): void {
    expect(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getWarnings())->toBe([]);
});

it('offers a fixer only where the rewrite is provably safe', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php');

    $fixableByLine = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $fixableByLine[$line][] = $violation['fixable'];
            }
        }
    }

    ksort($fixableByLine);

    expect($fixableByLine)->toBe([
        23 => [true],
        30 => [true, true],
        69 => [true],
        70 => [true],
        73 => [true],
        74 => [true],
        77 => [true],
        80 => [true],
        81 => [true],
        84 => [true, true],
        88 => [true],
        93 => [false],
        96 => [false],
        100 => [false],
        132 => [false],
        135 => [false],
        138 => [false],
        142 => [true],
        146 => [false],
        149 => [true],
        150 => [true],
        155 => [true],
        156 => [true],
        196 => [true],
        213 => [false],
        214 => [false],
        215 => [false],
        222 => [false],
        223 => [false],
        251 => [true],
        270 => [true],
        287 => [true],
        302 => [true],
        311 => [true],
    ]);

    expect($file->getErrorCount())->toBe(36)
        ->and($file->getFixableCount())->toBe(24);
});

it('explains a violation declined for an unnameable later argument', function (int $line): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages)->toHaveKey($line)
        ->and($messages[$line][0])->toContain('cannot be fixed automatically')
        ->and($messages[$line][0])->toContain('cannot be named');
})->with([93, 96, 100]);

it('explains a violation declined for runtime dispatch', function (int $line): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages)->toHaveKey($line)
        ->and($messages[$line][0])->toContain('cannot be fixed automatically')
        ->and($messages[$line][0])->toContain('dispatched against the runtime class');
})->with([132, 135, 138, 146, 213, 214, 215, 222, 223]);

it('names the parameter to use in a fixable violation', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(NO_NULL_ARGUMENTS, 'failing.php')->getErrors());

    expect($messages[302][0])->toContain('$retries')
        ->and($messages[302][0])->toContain('retries: null');
});

it('stops resolution at the namespace boundary', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'namespaces.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        27 => [NO_NULL_ARGUMENTS_POSITIONAL],
        28 => [NO_NULL_ARGUMENTS_POSITIONAL],
    ]);
});

it('leaves only the declined violations in its fixed output', function (): void {
    $file = analyzeFixture(NO_NULL_ARGUMENTS, 'autofixed.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([93, 96, 100, 132, 135, 138, 146, 213, 214, 215, 222, 223])
        ->and($file->getFixableCount())->toBe(0);
});
