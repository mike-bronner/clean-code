<?php

declare(strict_types=1);

const PROPERTY_PROMOTION = 'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion';

$promotionCounts = static function (string $fixture): array {
    $file = analyzeWithMasterRuleset(
            fixturePath('RequireConstructorPropertyPromotionSniff', $fixture)
        );
    $counts = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                if (str_starts_with($error['source'], PROPERTY_PROMOTION . '.') === true) {
                    $counts[$line] = ($counts[$line] ?? 0) + 1;
                }
            }
        }
    }

    ksort($counts);

    return $counts;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PROPERTY_PROMOTION);
});

it('produces no violations on the compliant fixture', function () use ($promotionCounts): void {
    expect($promotionCounts('passing.php'))->toBe([]);
});

it('flags violations at the exact line', function () use ($promotionCounts): void {
    expect($promotionCounts('failing.php'))->toBe([
        7 => 1,
        19 => 1,
        30 => 1,
        42 => 1,
        54 => 1,
    ]);
});

it('promotes properties carrying every modifier when fixed', function (): void {
    $file = analyzeFixture(PROPERTY_PROMOTION, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('RequireConstructorPropertyPromotionSniff', 'autofixed.php')));
});

it('passes the sniff with zero violations after fixing', function () use ($promotionCounts): void {
    expect($promotionCounts('autofixed.php'))->toBe([]);
});
