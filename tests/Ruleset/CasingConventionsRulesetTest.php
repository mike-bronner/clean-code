<?php

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
        16 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
        17 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
        18 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
        20 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
        22 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
        24 => ['Squiz.Classes.ValidClassName.NotPascalCase'],
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
