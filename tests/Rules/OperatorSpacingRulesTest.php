<?php

declare(strict_types=1);

const SQUIZ_OPERATOR_SPACING = 'CleanCode.Operators.BinaryOperatorSpacing';

const SQUIZ_CONCAT_SPACING = 'Squiz.Strings.ConcatenationSpacing';

const BOUNDARY_EXCLUSIONS = [
    16 => "3=>'three',",
    17 => "default=>'other',",
    19 => '$g=& $a;',
    21 => 'int $h=1',
    26 => 'int &$i',
];

$spacingFixture = static fn (string $fixture) => analyzeRulesetFixture(
    [SQUIZ_OPERATOR_SPACING, SQUIZ_CONCAT_SPACING],
    'OperatorSpacing',
    $fixture
);

it('registers the configured spacing sniffs in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SQUIZ_OPERATOR_SPACING)
        ->and($ruleset->sniffCodes)->toHaveKey(SQUIZ_CONCAT_SPACING);
});

it('makes both custom operator sniffs reachable through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey('CleanCode.Operators.NotOperatorSpacing')
        ->and($ruleset->sniffCodes)->toHaveKey('CleanCode.Operators.OperatorLineBreak');
});

it('raises no spacing violations on the compliant fixture', function () use ($spacingFixture): void {
    $file = $spacingFixture('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags missing and extra spacing at the expected lines', function () use ($spacingFixture): void {
    $byLine = violationSourcesByLine($spacingFixture('failing.php')->getErrors());

    expect(array_keys($byLine))->toBe([5, 6, 7, 8, 9, 10, 11, 12, 13, 14]);

    foreach ([5, 6, 8, 9, 10, 11, 12, 13, 14] as $line) {
        foreach ($byLine[$line] as $source) {
            expect($source)->toStartWith(SQUIZ_OPERATOR_SPACING);
        }
    }
});

it('stays silent on every documented boundary exclusion', function () use ($spacingFixture): void {
    $source = file(fixturePath('_rulesets/OperatorSpacing', 'passing.php'));
    $counts = violationCountsByLine($spacingFixture('passing.php')->getErrors());

    foreach (BOUNDARY_EXCLUSIONS as $line => $construct) {
        expect($source[$line - 1])->toContain($construct)
            ->and($counts[$line] ?? 0)->toBe(0);
    }
});

it('counts each broken link of a chained assignment separately', function () use ($spacingFixture): void {
    $counts = violationCountsByLine($spacingFixture('failing.php')->getErrors());

    expect($counts[13])->toBe(4)
        ->and($counts[14])->toBe(2);
});

it('flags the registered half of each boundary pair', function () use ($spacingFixture): void {
    $errors = $spacingFixture('failing.php')->getErrors();
    $counts = violationCountsByLine($errors);
    $sources = violationSourcesByLine($errors);

    expect($counts[10])->toBe(2)
        ->and($counts[11])->toBe(1)
        ->and($counts[12])->toBe(1)
        ->and($sources[11])->toBe([SQUIZ_OPERATOR_SPACING . '.NoSpaceAfter'])
        ->and($sources[12])->toBe([SQUIZ_OPERATOR_SPACING . '.NoSpaceAfter']);
});

it('flags extra space before an assignment', function () use ($spacingFixture): void {
    $byLine = violationSourcesByLine($spacingFixture('failing.php')->getErrors());

    expect($byLine[9] ?? [])->toBe([SQUIZ_OPERATOR_SPACING . '.SpacingBefore']);
});

it('stays silent on operator-led continuation lines', function () use ($spacingFixture): void {
    $file = $spacingFixture('wrapped.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('enforces concatenation spacing to one space', function () use ($spacingFixture): void {
    $byLine = violationSourcesByLine($spacingFixture('failing.php')->getErrors());

    foreach ($byLine[7] as $source) {
        expect($source)->toStartWith(SQUIZ_CONCAT_SPACING);
    }
});

it('auto-fixes spacing violations to one space each side', function () use ($spacingFixture): void {
    $file = $spacingFixture('failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('_rulesets/OperatorSpacing', 'autofixed.php')));
});
