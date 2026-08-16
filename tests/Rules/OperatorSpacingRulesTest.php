<?php

/**
 * Tests the binary-operator and concatenation spacing configured in the master
 * rules.xml for "Arrays: Operator spacing & line breaks" (#35): the bundled
 * CleanCode.Operators.BinaryOperatorSpacing (the Squiz.WhiteSpace.OperatorSpacing
 * subclass rules.xml wires in for #64's sake) and Squiz.Strings.ConcatenationSpacing
 * sniffs enforce exactly one space on each side. It also pins that both custom
 * CleanCode.Operators.* sniffs are reachable through the master ruleset.
 *
 * The two spacing sniffs are isolated together (loaded from rules.xml with
 * their configured properties, then $ruleset->sniffs narrowed to the pair) so
 * the behaviour and autofix assertions are unaffected by sibling standards.
 * Because the standard is implemented by two sniffs rather than one, its
 * fixtures live in tests/fixtures/_rulesets/OperatorSpacing/ rather than in a
 * per-sniff directory.
 *
 * The probe lines in failing.php are line 5 (arithmetic), 6 (comparison),
 * 7 (concatenation), 8 (extra-padded arithmetic) and 9 (extra-padded
 * assignment — exercises ignoreSpacingBeforeAssignments="false"). wrapped.php
 * probes ignoreNewlines.
 */

declare(strict_types=1);

const SQUIZ_OPERATOR_SPACING = 'CleanCode.Operators.BinaryOperatorSpacing';

const SQUIZ_CONCAT_SPACING = 'Squiz.Strings.ConcatenationSpacing';

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

    expect(array_keys($byLine))->toBe([5, 6, 7, 8, 9]);

    foreach ([5, 6, 8, 9] as $line) {
        foreach ($byLine[$line] as $source) {
            expect($source)->toStartWith(SQUIZ_OPERATOR_SPACING);
        }
    }
});

/**
 * ignoreSpacingBeforeAssignments="false" makes the sniff police the space
 * before "=" too, so alignment padding (`$e  = 1;`, line 9) is flagged.
 * With the property at Squiz's default that line is silent — this pins the
 * one property #35 adds to the sniff.
 */
it('flags extra space before an assignment', function () use ($spacingFixture): void {
    $byLine = violationSourcesByLine($spacingFixture('failing.php')->getErrors());

    expect($byLine[9] ?? [])->toBe([SQUIZ_OPERATOR_SPACING . '.SpacingBefore']);
});

/**
 * ignoreNewlines="true" keeps both spacing sniffs silent on an operator
 * that leads a wrapped continuation line — the very layout the line-break
 * standard mandates. Without it they would flag the operator-led lines
 * ("Expected 1 space before …; newline found").
 */
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
