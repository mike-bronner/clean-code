<?php

/**
 * Integration test for the Slevomat TypeHints rules wired into the master
 * ruleset (rules.xml). It runs the shipped ruleset, so it covers the combined
 * TypeHints surface: PropertyTypeHint (owned by the "Type Hints and Return
 * Types" standard, #45) plus ParameterTypeHint / ReturnTypeHint (owned by the
 * "Methods: Type Hints" standard, #70). This test guards that the master
 * ruleset wires all three and flags them at the expected lines, regardless of
 * which standard each sniff belongs to.
 *
 * Fixtures live in tests/fixtures/_rulesets/TypeHints/ — the standard is
 * implemented by three sniffs rather than one, so it gets a _rulesets bucket
 * rather than a per-sniff directory. passing.php must produce zero TypeHints
 * violations, failing.php must be flagged at the exact lines below, and
 * autofixed.php is the expected phpcbf output: every violation the sniff can
 * infer a native hint for is resolved, the rest remain flagged.
 *
 * Only SlevomatCodingStandard.TypeHints.* sources are asserted on. The
 * fixtures deliberately pack interface/abstract/class cases into a single
 * namespace-less file, so PSR1's one-class-per-file / namespace rules (and
 * any other standard wired into the shared master ruleset) also fire on
 * them — those are out of scope here and are filtered out, so unrelated
 * additions to rules.xml cannot break this test.
 *
 * excluded-codes.php carries the two message codes rules.xml excludes from
 * PropertyTypeHint. Both halves are pinned, per CONTRIBUTING.md: the codes stay
 * silent through rules.xml, and the same fixture proves they would fire without
 * the excludes, so dropping an <exclude> fails this suite.
 */

declare(strict_types=1);

const PARAMETER_MISSING_ANY = 'SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint';

const PARAMETER_MISSING_NATIVE = 'SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint';

const RETURN_MISSING_ANY = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint';

const RETURN_MISSING_NATIVE = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint';

const PROPERTY_MISSING_ANY = 'SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint';

const PROPERTY_MISSING_NATIVE = 'SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint';

const TYPE_HINTS_SNIFFS = [
    'SlevomatCodingStandard.TypeHints.ParameterTypeHint',
    'SlevomatCodingStandard.TypeHints.ReturnTypeHint',
    'SlevomatCodingStandard.TypeHints.PropertyTypeHint',
];

const PROPERTY_TYPE_HINT_SNIFF = 'SlevomatCodingStandard.TypeHints.PropertyTypeHint';

/**
 * The two codes rules.xml excludes from PropertyTypeHint. Both police docblock
 * hygiene rather than a missing native hint, so #45 drops them — the same
 * reasoning that drops five codes from #70's two sniffs, pinned the same way in
 * tests/Ruleset/MethodTypeHintsRulesetTest.php.
 */
const PROPERTY_TYPE_HINT_EXCLUDED_CODES = [
    PROPERTY_TYPE_HINT_SNIFF . '.MissingTraversableTypeHintSpecification',
    PROPERTY_TYPE_HINT_SNIFF . '.UselessAnnotation',
];

// Reporting runs through the whole master ruleset and is then scoped to the
// TypeHints sources; only the fixer assertion narrows the ruleset, so no other
// auto-fixing rule can alter the byte-compared output.
$typeHintsReport = static function (string $fixture): array {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', $fixture));
    $sources = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                if (str_starts_with($error['source'], 'SlevomatCodingStandard.TypeHints.') === true) {
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
                $isTypeHints = str_starts_with($error['source'], 'SlevomatCodingStandard.TypeHints.');

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

/**
 * The warning half is scoped to the TypeHints sources rather than asserting the
 * whole file silent: both sniffs only ever addError today, so a Slevomat release
 * or severity change that started emitting a warning would slip past the error
 * map above — while an unrelated warning from another standard says nothing
 * about these excludes.
 */
it('keeps the excluded property codes silent through the master ruleset', function () use ($typeHintsReport): void {
    expect($typeHintsReport('excluded-codes.php'))->toBe([]);

    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/TypeHints', 'excluded-codes.php'));
    $warnings = violationSourcesByLine($file->getWarnings());
    $typeHintsWarnings = array_filter(
        $warnings === [] ? [] : array_merge(...array_values($warnings)),
        static fn (string $source): bool => str_starts_with($source, 'SlevomatCodingStandard.TypeHints.')
    );

    expect($typeHintsWarnings)->toBe([]);
});

/**
 * Guards the test above from passing vacuously: the same fixture, run through
 * the same ruleset with only the excludes lifted, must raise both excluded
 * codes — and nowhere else. Without this, a fixture that trips nothing at all
 * looks exactly like a working exclude list.
 *
 * The whole map is asserted rather than membership alone, so a code that moved
 * to another property, or a third code appearing beside the two, reddens here
 * rather than hiding behind a satisfied toContain().
 */
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
