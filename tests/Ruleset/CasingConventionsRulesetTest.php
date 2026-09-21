<?php

/**
 * Integration test for the naming casing-convention rules (issue #22) that
 * the master CleanCode/ruleset.xml enforces:
 *
 * - Squiz.NamingConventions.ValidVariableName — camelCase variables and
 *   properties (PrivateNoUnderscore excluded: no underscore prefix demanded).
 *   The one sniff of the three CleanCode/ruleset.xml references explicitly.
 * - PSR1.Methods.CamelCapsMethodName — camelCase methods (magic exempt).
 * - Squiz.Classes.ValidClassName — PascalCase classes, interfaces, traits,
 *   and enums (abstract classes are covered via their class token).
 *
 * The last two carry no explicit ref in CleanCode/ruleset.xml (#217): they reach the
 * ruleset through its PSR12 ref, which includes PSR1 wholesale. The line map
 * below therefore also pins that transitive path — drop the PSR12 ref, or
 * exclude either sniff from it, and the expected violations disappear.
 *
 * All three sniffs are non-strict about consecutive capitals, so acronym
 * runs pass: $userID, getUserID(), and HTTPClient are all accepted alongside
 * $userId, getUserId(), and HttpClient. Leading underscores on private
 * members and on locals inside class scope are stripped by the Squiz sniff
 * before the camelCaps check, so private $_legacy and $_inClass slip
 * through — a documented limitation, asserted below so a behavior change
 * surfaces here.
 *
 * Because the standard is carried by three sniffs rather than one, its fixture
 * lives in tests/fixtures/_rulesets/CasingConventions/failing.php. Violations
 * from rules other than the three above are ignored, so unrelated additions to
 * the master ruleset cannot break this test.
 */

declare(strict_types=1);

const VALID_VARIABLE_NAME = 'Squiz.NamingConventions.ValidVariableName';

const NAMING_SNIFFS = [
    VALID_VARIABLE_NAME,
    'PSR1.Methods.CamelCapsMethodName',
    'Squiz.Classes.ValidClassName',
];

$namingViolations = static function (): array {
    $file = analyzeWithMasterRuleset(fixturePath('_rulesets/CasingConventions', 'failing.php'));
    $violations = [];

    foreach ($file->getErrors() as $line => $columns) {
        foreach ($columns as $errors) {
            foreach ($errors as $error) {
                foreach (NAMING_SNIFFS as $sniff) {
                    if (strpos($error['source'], $sniff . '.') === 0) {
                        $violations[$line][] = $error['source'];
                    }
                }
            }
        }
    }

    ksort($violations);

    return $violations;
};

it('flags exactly the expected lines', function () use ($namingViolations): void {
    expect($namingViolations())->toBe([
        8 => [VALID_VARIABLE_NAME . '.NotCamelCaps'],
        9 => [VALID_VARIABLE_NAME . '.NotCamelCaps'],
        10 => [VALID_VARIABLE_NAME . '.NotCamelCaps'],
        11 => [VALID_VARIABLE_NAME . '.NotCamelCaps'],
        16 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        17 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        18 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        20 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        22 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        24 => ['Squiz.Classes.ValidClassName.NotCamelCaps'],
        34 => [VALID_VARIABLE_NAME . '.MemberNotCamelCaps'],
        35 => [VALID_VARIABLE_NAME . '.MemberNotCamelCaps'],
        36 => [VALID_VARIABLE_NAME . '.PublicHasUnderscore'],
        43 => [VALID_VARIABLE_NAME . '.NotCamelCaps'],
        48 => ['PSR1.Methods.CamelCapsMethodName.NotCamelCaps'],
        49 => ['PSR1.Methods.CamelCapsMethodName.NotCamelCaps'],
    ]);
});

it('does not demand an underscore prefix on private properties', function () use ($namingViolations): void {
    $sources = array_merge(...array_values($namingViolations()) ?: [[]]);

    expect($sources)->not->toContain(
        VALID_VARIABLE_NAME . '.PrivateNoUnderscore',
        'PrivateNoUnderscore must stay excluded from the master ruleset.'
    );
});
