<?php

/**
 * Methods: Type Hints (#70) — the Slevomat ParameterTypeHint and ReturnTypeHint
 * rules as configured in the master ruleset (rules.xml). Every function-like
 * declaration must carry native parameter and return type hints, so the sniffs
 * cover class methods, closures, and free functions alike.
 *
 * Fixtures live in tests/fixtures/_rulesets/MethodTypeHints/ — the standard is
 * carried by two sniffs rather than one, so it gets a _rulesets bucket rather
 * than a per-sniff directory. passing.php must produce no violation from either
 * sniff, failing.php must be flagged at the exact lines below, and autofixed.php
 * is the fixer's own output on failing.php: every violation whose hint the sniff
 * can infer from an annotation is resolved, the rest remain flagged.
 *
 * Reporting runs the whole master ruleset, so the message-level exclusions and
 * the pinned enable* properties stay live — narrowing by Config::$sniffs would
 * drop the exclusions and test a configuration that never ships. The fixtures
 * pack several classes into one namespace-less file, so PSR1/PSR12 and other
 * rules fire on them too; assertions are therefore scoped to the two sniffs #70
 * owns.
 *
 * That scope is an explicit allowlist, not a SlevomatCodingStandard.TypeHints.
 * prefix: #45's PropertyTypeHint shares the namespace and is live in the same
 * shipped ruleset. property-hint-not-counted.php pins that boundary — a prefix
 * match would pull its property error into #70's map.
 *
 * The docblock-only fixture cases (union, intersection, mixed, object, static,
 * never, standalone true/false/null, nullable) are what hold the enable* pins
 * in rules.xml: each annotation is promoted to a native hint only while its flag
 * is on, so flipping a pin changes either the line map below or autofixed.php.
 *
 * excluded-codes.php holds the other half of the configuration — the five
 * message codes rules.xml excludes. Both halves are pinned, per CONTRIBUTING.md:
 * the codes stay silent through rules.xml, and the same fixture proves they
 * would fire without the excludes, so dropping an <exclude> fails this suite.
 */

declare(strict_types=1);

const METHOD_TYPE_HINTS_SNIFFS = [
    'CleanCode.TypeHints.ParameterTypeHint',
    'SlevomatCodingStandard.TypeHints.ReturnTypeHint',
];

const PARAMETER_ANY = 'CleanCode.TypeHints.ParameterTypeHint.MissingAnyTypeHint';

const PARAMETER_NATIVE = 'CleanCode.TypeHints.ParameterTypeHint.MissingNativeTypeHint';

const RETURN_ANY = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint';

const RETURN_NATIVE = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint';

const PARAMETER_TRAVERSABLE = 'CleanCode.TypeHints.ParameterTypeHint'
    . '.MissingTraversableTypeHintSpecification';

const PARAMETER_USELESS = 'CleanCode.TypeHints.ParameterTypeHint.UselessAnnotation';

const RETURN_TRAVERSABLE = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint'
    . '.MissingTraversableTypeHintSpecification';

const RETURN_USELESS = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.UselessAnnotation';

const RETURN_LESS_SPECIFIC = 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.LessSpecificNativeTypeHint';

/**
 * The five codes rules.xml excludes from the two sniffs #70 owns. Each acts on
 * what a docblock says rather than on a missing native hint, so the standard
 * drops it.
 */
const METHOD_TYPE_HINTS_EXCLUDED_CODES = [
    PARAMETER_TRAVERSABLE,
    PARAMETER_USELESS,
    RETURN_TRAVERSABLE,
    RETURN_USELESS,
    RETURN_LESS_SPECIFIC,
];

/**
 * True when a violation source belongs to one of the two sniffs #70 owns.
 */
$ownedSniff = static function (string $source): bool {
    foreach (METHOD_TYPE_HINTS_SNIFFS as $sniff) {
        if (str_starts_with($source, $sniff . '.') === true) {
            return true;
        }
    }

    return false;
};

/**
 * The fixture's #70 errors as a line => sorted sources map, gathered from a
 * whole-ruleset run.
 */
$ownedReport = static function (string $fixture) use ($ownedSniff): array {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/MethodTypeHints', $fixture));
    $sources = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                if ($ownedSniff($error['source']) === true) {
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

/**
 * The fixture's #70 warnings. Both sniffs only ever addError today, so this is
 * the forward guard: a Slevomat release or severity change that starts emitting
 * a warning would slip past the error map above.
 */
$ownedWarningCount = static function (string $fixture) use ($ownedSniff): int {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/MethodTypeHints', $fixture));
    $count = 0;

    foreach ($file->getWarnings() as $columns) {
        foreach ($columns as $warnings) {
            foreach ($warnings as $warning) {
                if ($ownedSniff($warning['source']) === true) {
                    $count++;
                }
            }
        }
    }

    return $count;
};

it('registers both method type-hint rules in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    foreach (METHOD_TYPE_HINTS_SNIFFS as $sniff) {
        expect($ruleset->sniffCodes)->toHaveKey($sniff);
    }
});

it('produces no violations on the compliant fixture', function () use ($ownedReport, $ownedWarningCount): void {
    expect($ownedReport('passing.php'))->toBe([]);
    expect($ownedWarningCount('passing.php'))->toBe(0);
});

it('flags violations at the exact line', function () use ($ownedReport, $ownedWarningCount): void {
    expect($ownedReport('failing.php'))->toBe([
        6 => [PARAMETER_ANY],
        14 => [PARAMETER_NATIVE],
        23 => [RETURN_ANY],
        31 => [RETURN_NATIVE],
        36 => [RETURN_NATIVE],
        41 => [PARAMETER_ANY, RETURN_ANY],
        50 => [PARAMETER_ANY],
        59 => [RETURN_ANY],
        70 => [RETURN_NATIVE],
        86 => [PARAMETER_NATIVE],
        94 => [PARAMETER_NATIVE],
        102 => [PARAMETER_NATIVE],
        110 => [RETURN_NATIVE],
        118 => [RETURN_NATIVE],
        127 => [PARAMETER_ANY, RETURN_ANY],
        139 => [PARAMETER_NATIVE],
        147 => [RETURN_NATIVE],
        162 => [RETURN_NATIVE],
        170 => [RETURN_NATIVE],
        178 => [RETURN_NATIVE],
        197 => [PARAMETER_NATIVE],
        205 => [RETURN_NATIVE],
        213 => [PARAMETER_NATIVE],
        221 => [PARAMETER_NATIVE],
        229 => [RETURN_NATIVE],
        237 => [RETURN_NATIVE],
        252 => [PARAMETER_NATIVE],
    ]);

    expect($ownedWarningCount('failing.php'))->toBe(0);
});

it('leaves #45 property type-hint violations out of #70 coverage', function () use ($ownedReport): void {
    // Both sniffs fire on the unhinted method at line 13; the unhinted property
    // at line 11 is PropertyTypeHint, which #45 owns and this map must not see.
    expect($ownedReport('property-hint-not-counted.php'))->toBe([
        13 => [PARAMETER_ANY, RETURN_ANY],
    ]);

    $sources = allViolationSourcesByLine(
        analyzeWithMasterRuleset(fixturePath('_rulesets/MethodTypeHints', 'property-hint-not-counted.php'))
    );

    expect($sources[11])->toContain('CleanCode.TypeHints.PropertyTypeHint.MissingAnyTypeHint');
});

it('marks exactly the annotated violations fixable', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/MethodTypeHints', 'failing.php'));
    $lines = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                $isOwned = str_starts_with($error['source'], 'CleanCode.TypeHints.ParameterTypeHint.')
                    || str_starts_with($error['source'], 'SlevomatCodingStandard.TypeHints.ReturnTypeHint.');

                if ($error['fixable'] === true && $isOwned === true) {
                    $lines[] = $line;
                }
            }
        }
    }

    sort($lines);

    expect(array_values(array_unique($lines)))->toBe(
        [14, 31, 36, 70, 86, 94, 102, 110, 118, 139, 147, 162, 170, 178, 197, 205, 213, 221, 229, 237, 252],
        'Only a hint the sniff can infer — from an annotation, or a body that returns nothing — is fixable.'
    );
});

it('resolves every inferrable hint when fixed', function (): void {
    $file = analyzeRulesetFixture(METHOD_TYPE_HINTS_SNIFFS, 'MethodTypeHints', 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/MethodTypeHints', 'autofixed.php')));
});

it('keeps the excluded codes silent through the master ruleset', function () use (
    $ownedReport,
    $ownedWarningCount
): void {
    expect($ownedReport('excluded-codes.php'))->toBe([]);
    expect($ownedWarningCount('excluded-codes.php'))->toBe(0);
});

/**
 * Guards the test above from passing vacuously: the same fixture, run through
 * the same ruleset with only the excludes lifted, must raise every excluded
 * code — and nowhere else. Without this, a fixture that trips nothing at all
 * looks exactly like a working exclude list.
 *
 * The whole map is asserted rather than membership alone, so a code that moved
 * to another declaration, or a sixth code appearing beside the five, reddens
 * here rather than hiding behind a satisfied toContain().
 */
it('raises every excluded code without the master rulesets excludes', function (): void {
    $file = analyzeWithoutExcludes(
        METHOD_TYPE_HINTS_SNIFFS,
        METHOD_TYPE_HINTS_EXCLUDED_CODES,
        fixturePath('_rulesets/MethodTypeHints', 'excluded-codes.php')
    );

    expect(allViolationSourcesByLine($file))->toBe([
        12 => [PARAMETER_TRAVERSABLE],
        18 => [PARAMETER_USELESS],
        29 => [RETURN_TRAVERSABLE],
        35 => [RETURN_USELESS],
        50 => [RETURN_LESS_SPECIFIC],
    ]);
});

it('leaves only the uninferrable violations after fixing', function () use ($ownedReport): void {
    expect($ownedReport('autofixed.php'))->toBe([
        6 => [PARAMETER_ANY],
        23 => [RETURN_ANY],
        41 => [PARAMETER_ANY, RETURN_ANY],
        50 => [PARAMETER_ANY],
        59 => [RETURN_ANY],
        127 => [PARAMETER_ANY, RETURN_ANY],
    ]);
});
