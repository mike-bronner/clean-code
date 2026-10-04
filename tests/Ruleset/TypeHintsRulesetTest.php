<?php

declare(strict_types=1);

const PARAMETER_MISSING_ANY = 'CleanCode.TypeHints.ParameterTypeHint.MissingAnyTypeHint';

const PARAMETER_MISSING_NATIVE = 'CleanCode.TypeHints.ParameterTypeHint.MissingNativeTypeHint';

const RETURN_MISSING_ANY = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint';

const RETURN_MISSING_NATIVE = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint';

const PROPERTY_MISSING_ANY = 'CleanCode.TypeHints.PropertyTypeHint.MissingAnyTypeHint';

const PROPERTY_MISSING_NATIVE = 'CleanCode.TypeHints.PropertyTypeHint.MissingNativeTypeHint';

const TYPE_HINTS_SNIFFS = [
    'CleanCode.TypeHints.ParameterTypeHint',
    'SlevomatCodingStandard.TypeHints.ReturnTypeHint',
    'CleanCode.TypeHints.PropertyTypeHint',
];

const PROPERTY_TYPE_HINT_SNIFF = 'CleanCode.TypeHints.PropertyTypeHint';

const PROPERTY_TYPE_HINT_EXCLUDED_CODES = [
    PROPERTY_TYPE_HINT_SNIFF . '.MissingTraversableTypeHintSpecification',
    PROPERTY_TYPE_HINT_SNIFF . '.UselessAnnotation',
];

$typeHintsReport = static function (string $fixture): array {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', $fixture));
    $sources = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                if (isTypeHintsSource($error['source']) === true) {
                    $sources[$line][] = $error['source'];
                }
            }
        }
    }

    foreach ($sources as &$lineSources) {
        sort($lineSources);
    }

    unset($lineSources);
    ksort($sources);

    return $sources;
};

it('registers every TypeHints rule in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    foreach (TYPE_HINTS_SNIFFS as $sniff) {
        expect($ruleset->sniffCodes)->toHaveKey($sniff);
    }
});

it('produces no violations on the compliant fixture', function () use ($typeHintsReport): void {
    expect($typeHintsReport('passing.php'))->toBe([]);
});

it('flags violations at the exact line', function () use ($typeHintsReport): void {
    expect($typeHintsReport('failing.php'))->toBe([
        5 => [PROPERTY_MISSING_ANY],
        10 => [PROPERTY_MISSING_NATIVE],
        12 => [PARAMETER_MISSING_ANY],
        20 => [PARAMETER_MISSING_NATIVE],
        25 => [RETURN_MISSING_ANY],
        35 => [RETURN_MISSING_NATIVE],
        40 => [PARAMETER_MISSING_ANY],
        48 => [PARAMETER_MISSING_ANY],
        55 => [RETURN_MISSING_ANY],
        63 => [PARAMETER_MISSING_NATIVE],
    ]);

    expect(analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', 'failing.php'))->getWarnings())->toBe([]);
});

it('marks exactly the inferrable violations fixable', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', 'failing.php'));
    $lines = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                $isTypeHints = isTypeHintsSource($error['source']);

                if ($error['fixable'] === true && $isTypeHints === true) {
                    $lines[] = $line;
                }
            }
        }
    }

    sort($lines);

    expect(array_values(array_unique($lines)))
        ->toBe([10, 20, 35, 63], 'Exactly the annotated (inferrable) violations must be fixable.');
});

it('resolves every inferrable hint when fixed', function (): void {
    $file = analyzeRulesetFixture(TYPE_HINTS_SNIFFS, 'TypeHints', 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/TypeHints', 'autofixed.php')));
});

it('leaves only the uninferrable violations after fixing', function () use ($typeHintsReport): void {
    expect($typeHintsReport('autofixed.php'))->toBe([
        5 => [PROPERTY_MISSING_ANY],
        12 => [PARAMETER_MISSING_ANY],
        25 => [RETURN_MISSING_ANY],
        40 => [PARAMETER_MISSING_ANY],
        48 => [PARAMETER_MISSING_ANY],
        55 => [RETURN_MISSING_ANY],
    ]);
});

it('keeps the excluded property codes silent through the master ruleset', function () use ($typeHintsReport): void {
    expect($typeHintsReport('excluded-codes.php'))->toBe([]);

    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', 'excluded-codes.php'));
    $warnings = violationSourcesByLine($file->getWarnings());
    $typeHintsWarnings = array_filter(
            $warnings === [] ? [] : array_merge(...array_values($warnings)),
            static fn (string $source): bool => isTypeHintsSource($source)
        );

    expect($typeHintsWarnings)->toBe([]);
});

it('raises every excluded property code without the master rulesets excludes', function (): void {
    $file = analyzeWithoutExcludes(
            [PROPERTY_TYPE_HINT_SNIFF],
            PROPERTY_TYPE_HINT_EXCLUDED_CODES,
            fixturePath('_rulesets/TypeHints', 'excluded-codes.php')
        );

    expect(allViolationSourcesByLine($file))->toBe([
        12 => [PROPERTY_TYPE_HINT_SNIFF . '.MissingTraversableTypeHintSpecification'],
        17 => [PROPERTY_TYPE_HINT_SNIFF . '.UselessAnnotation'],
    ]);
});
