<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\AttributeCasts;

$returnVerdicts = static function (AttributeCasts $attributeCasts): array {
    $file = parseFixture('AttributeCasts', 'returns.php');
    $verdicts = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] === T_FUNCTION) {
            $verdicts[$file->getDeclarationName($pointer)] = $attributeCasts->isReturnedBy($file, $pointer);
        }
    }

    return $verdicts;
};

it('answers true only for a return type that resolves to the Eloquent Attribute cast', function () use (
    $returnVerdicts,
): void {
    expect($returnVerdicts(new AttributeCasts()))->toBe([
        'imported' => true,
        'aliased' => true,
        'fullyQualified' => true,
        'nullable' => true,
        'otherAlias' => false,
        'otherFullyQualified' => false,
        'untyped' => false,
    ]);
});

it('reads the imports once per token stream', function () use ($returnVerdicts): void {
    $attributeCasts = new AttributeCasts();

    $returnVerdicts($attributeCasts);
    $returnVerdicts($attributeCasts);

    expect($attributeCasts->importCounts())->toBe(['builds' => 2, 'hits' => 12]);
});
