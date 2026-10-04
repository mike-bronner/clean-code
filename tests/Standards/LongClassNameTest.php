<?php

declare(strict_types=1);

const LONG_CLASS_NAME = 'CleanCode.Naming.LongClassName';

const LONG_CLASS_NAME_SUBTRACTIONS = [
    'subtractPrefixes' => 'Abstract',
    'subtractSuffixes' => 'Repository,Factory,MockRepository',
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LONG_CLASS_NAME);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every over-length declaration at its keyword', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 10, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 14, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 19, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 26, 'column' => 1, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
        ['line' => 31, 'column' => 7, 'source' => 'CleanCode.Naming.LongClassName.TooLong'],
    ])->and($file->getWarnings())->toBe([]);
});

it('names the type, its length, and the threshold', function (): void {
    $errors = analyzeFixture(LONG_CLASS_NAME, 'failing.php')->getErrors();

    expect($errors[10][1][0]['message'])
        ->toBe('Name CustomerAddressBookSynchronizationHandler is 41 characters long; keep it to 40 or fewer');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getFixableCount())->toBe(0);
});

it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reads the maximum from its property', function (): void {
    $file = analyzeFixture(
            LONG_CLASS_NAME,
            'passing.php',
            static function (object $sniff): void {
                $sniff->maximum = 39;
            }
        );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        14 => ['CleanCode.Naming.LongClassName.TooLong'],
        49 => ['CleanCode.Naming.LongClassName.TooLong'],
        54 => ['CleanCode.Naming.LongClassName.TooLong'],
        62 => ['CleanCode.Naming.LongClassName.TooLong'],
    ]);
});

it('stays silent at exactly the maximum', function (): void {
    $file = analyzeFixture(
            LONG_CLASS_NAME,
            'failing.php',
            static function (object $sniff): void {
                $sniff->maximum = 41;
            }
        );

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([31 => ['CleanCode.Naming.LongClassName.TooLong']]);
});

it('flags every name in the subtraction fixture without the lists configured', function (): void {
    $file = analyzeFixture(LONG_CLASS_NAME, 'subtraction.php');

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([13, 18, 24, 30, 37, 43]);
});

it('subtracts one prefix and one suffix, each the first that matches', function (): void {
    $file = analyzeFixture(
            LONG_CLASS_NAME,
            'subtraction.php',
            static function (object $sniff): void {
                $sniff->subtractPrefixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractPrefixes'];
                $sniff->subtractSuffixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractSuffixes'];
            }
        );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        37 => ['CleanCode.Naming.LongClassName.TooLong'],
        43 => ['CleanCode.Naming.LongClassName.TooLong'],
    ]);
});

it('stops at the first matching prefix', function (): void {
    $file = analyzeFixture(
            LONG_CLASS_NAME,
            'subtraction.php',
            static function (object $sniff): void {
                $sniff->subtractPrefixes = 'Abstract,AbstractWarehouse';
            }
        );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([18, 24, 30, 37, 43]);
});

it('reports the subtracted length', function (): void {
    $errors = analyzeFixture(
            LONG_CLASS_NAME,
            'subtraction.php',
            static function (object $sniff): void {
                $sniff->subtractPrefixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractPrefixes'];
                $sniff->subtractSuffixes = LONG_CLASS_NAME_SUBTRACTIONS['subtractSuffixes'];
            }
        )->getErrors();

    expect($errors[37][1][0]['message'])
        ->toBe('Name WarehouseInventoryReplenishmentAuditsMockRepository is 41 characters long; keep it to 40 or fewer')
        ->and($errors[43][10][0]['message'])
        ->toBe(
            'Name AbstractWarehouseInventoryReplenishmentAuditTrailRepository'
                . ' is 41 characters long; keep it to 40 or fewer'
        );
});

it('trims list entries and drops empty ones', function (): void {
    $file = analyzeFixture(
            LONG_CLASS_NAME,
            'subtraction.php',
            static function (object $sniff): void {
                $sniff->subtractPrefixes = ' , Abstract , ';
            }
        );

    expect(array_keys(violationSourcesByLine($file->getErrors())))
        ->toBe([18, 24, 30, 37, 43]);
});
