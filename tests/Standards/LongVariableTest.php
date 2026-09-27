<?php

declare(strict_types=1);

const LONG_VARIABLE = 'CleanCode.Naming.LongVariable';

const LONG_VARIABLE_TOO_LONG = 'CleanCode.Naming.LongVariable.TooLong';

const LONG_VARIABLE_SUBTRACTIONS = [
    'subtractPrefixes' => 'temporary',
    'subtractSuffixes' => 'Collection,Factory,MockCollection',
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LONG_VARIABLE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every over-length variable at its own token', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 22, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 18, 'column' => 26, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 24, 'column' => 47, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 28, 'column' => 34, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 30, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 32, 'column' => 14, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 36, 'column' => 28, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 45, 'column' => 29, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 49, 'column' => 10, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 60, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 62, 'column' => 30, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 69, 'column' => 16, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 71, 'column' => 16, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 79, 'column' => 36, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 86, 'column' => 34, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 88, 'column' => 9, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 94, 'column' => 37, 'source' => LONG_VARIABLE_TOO_LONG],
        ['line' => 96, 'column' => 5, 'source' => LONG_VARIABLE_TOO_LONG],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports each name once per container', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect(violationCountsByLine($file->getErrors()))
        ->toBe(array_fill_keys([16, 18, 24, 28, 30, 32, 36, 45, 49, 60, 62, 69, 71, 79, 86, 88, 94, 96], 1));
});

it('names the variable, its length, and the threshold', function (): void {
    $errors = analyzeFixture(LONG_VARIABLE, 'failing.php')->getErrors();

    expect($errors[16][22][0]['message'])
        ->toBe('Name $replenishmentWindowId is 21 characters long; keep it to 20 or fewer');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'failing.php');

    expect($file->getErrorCount())->toBe(18)
        ->and($file->getFixableCount())->toBe(0);
});

it('reads the maximum from its property', function (): void {
    $file = analyzeFixtureWithProperty(LONG_VARIABLE, 'passing.php', 'maximum', 19);

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        27 => [LONG_VARIABLE_TOO_LONG],
        32 => [LONG_VARIABLE_TOO_LONG],
        46 => [LONG_VARIABLE_TOO_LONG],
        48 => [LONG_VARIABLE_TOO_LONG],
        77 => [LONG_VARIABLE_TOO_LONG],
        88 => [LONG_VARIABLE_TOO_LONG],
        90 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

it('stays silent at exactly the maximum', function (): void {
    $file = analyzeFixtureWithProperty(LONG_VARIABLE, 'failing.php', 'maximum', 21);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every name in the subtraction fixture without the lists configured', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'subtraction.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([22, 27, 34, 40, 48, 59]);
});

it('subtracts one prefix and one suffix, each the first that matches', function (): void {
    $file = analyzeFixture(
        LONG_VARIABLE,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_VARIABLE_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_VARIABLE_SUBTRACTIONS['subtractSuffixes'];
        }
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        48 => [LONG_VARIABLE_TOO_LONG],
        59 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

it('takes its lists from ruleset properties', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        LONG_VARIABLE,
        'subtraction.php',
        LONG_VARIABLE_SUBTRACTIONS
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        48 => [LONG_VARIABLE_TOO_LONG],
        59 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

it('stops at the first matching prefix', function (): void {
    $file = analyzeFixtureWithProperty(
        LONG_VARIABLE,
        'subtraction.php',
        'subtractPrefixes',
        'temporary,temporaryWarehouseInventory'
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([27, 34, 48, 59]);
});

it('reports the subtracted length', function (): void {
    $errors = analyzeFixture(
        LONG_VARIABLE,
        'subtraction.php',
        static function (object $sniff): void {
            $sniff->subtractPrefixes = LONG_VARIABLE_SUBTRACTIONS['subtractPrefixes'];
            $sniff->subtractSuffixes = LONG_VARIABLE_SUBTRACTIONS['subtractSuffixes'];
        }
    )->getErrors();

    expect($errors[48][21][0]['message'])
        ->toBe('Name $warehouseAuditLogMockCollection is 21 characters long; keep it to 20 or fewer')
        ->and($errors[59][21][0]['message'])
        ->toBe(
            'Name $temporaryWarehouseInventoryAuditCollection'
            . ' is 23 characters long; keep it to 20 or fewer'
        );
});

it('trims list entries and drops empty ones', function (): void {
    $file = analyzeFixtureWithProperty(
        LONG_VARIABLE,
        'subtraction.php',
        'subtractPrefixes',
        ' , temporary , '
    );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([27, 34, 48, 59]);
});

it('reproduces PHPMD ordering of de-duplication and the member-access exemption', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'ordering.php');

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([41 => [LONG_VARIABLE_TOO_LONG]]);
});

it('measures a hooked property but not the insides of its hooks', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'property-hooks.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        28 => [LONG_VARIABLE_TOO_LONG],
        46 => [LONG_VARIABLE_TOO_LONG],
    ]);
});

it('diverges from PHPMD only on trait duplicates and string interpolation', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'divergences.php');

    expect(violationCountsByLine($file->getErrors()))
        ->toBe([26 => 1, 28 => 1, 30 => 1]);
});

it('walks a nested construct through its owner exactly once', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'nesting.php');

    expect(violationCountsByLine($file->getErrors()))->toBe([
        45 => 1,
        49 => 1,
        58 => 1,
        69 => 1,
        80 => 1,
        94 => 1,
        115 => 1,
        122 => 1,
        144 => 1,
        153 => 1,
        178 => 1,
    ]);
});

it('exempts a member access with a comment before its operator', function (): void {
    $file = analyzeFixture(LONG_VARIABLE, 'member-access-comments.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
