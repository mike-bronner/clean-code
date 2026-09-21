<?php

/**
 * Integration test for the Constructors: Property Promotion standard (#47),
 * enforced by Slevomat's RequireConstructorPropertyPromotion sniff wired into
 * the master ruleset (CleanCode/ruleset.xml).
 *
 * Fixtures live in tests/fixtures/RequireConstructorPropertyPromotionSniff/:
 * passing.php must produce zero property-promotion violations, failing.php must
 * be flagged at the exact property-declaration lines below, and autofixed.php is
 * the expected phpcbf output — every non-promoted property + constructor
 * assignment becomes a promoted constructor parameter, carrying its visibility,
 * readonly, and default onto the parameter.
 *
 * Only the RequireConstructorPropertyPromotion source is asserted on. The
 * fixtures pack several classes into one namespace-less file, so PSR1's
 * one-class-per-file rule (and any other standard wired into the shared master
 * ruleset) also fires on them — those are out of scope here and are filtered
 * out, so unrelated additions to CleanCode/ruleset.xml cannot break this test. The fixer
 * assertion likewise restricts the ruleset to this sniff (keeping its
 * master-ruleset configuration), so no other auto-fixing rule can alter the
 * fixed output.
 */

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
