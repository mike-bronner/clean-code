<?php

/**
 * Tests the binary-operator and concatenation spacing configured in the master
 * CleanCode/ruleset.xml for "Arrays: Operator spacing & line breaks" (#35): the bundled
 * CleanCode.Operators.BinaryOperatorSpacing (the Squiz.WhiteSpace.OperatorSpacing
 * subclass CleanCode/ruleset.xml wires in for #64's sake) and Squiz.Strings.ConcatenationSpacing
 * sniffs enforce exactly one space on each side. It also pins that both custom
 * CleanCode.Operators.* sniffs are reachable through the master ruleset.
 *
 * The two spacing sniffs are isolated together (loaded from CleanCode/ruleset.xml with
 * their configured properties, then $ruleset->sniffs narrowed to the pair) so
 * the behaviour and autofix assertions are unaffected by sibling standards.
 * Because the standard is implemented by two sniffs rather than one, its
 * fixtures live in tests/fixtures/_rulesets/OperatorSpacing/ rather than in a
 * per-sniff directory.
 *
 * The probe lines in failing.php are line 5 (arithmetic), 6 (comparison),
 * 7 (concatenation), 8 (extra-padded arithmetic), 9 (extra-padded
 * assignment — exercises ignoreSpacingBeforeAssignments="false"), 10
 * (array-literal "=>"), 11 and 12 (a unary sign directly after "="), 13 (a
 * fully-unspaced assignment chain) and 14 (a chain whose first link alone is
 * unspaced). wrapped.php probes ignoreNewlines, and passing.php carries the
 * boundary exclusions listed in BOUNDARY_EXCLUSIONS below.
 */

declare(strict_types=1);

const SQUIZ_OPERATOR_SPACING = 'CleanCode.Operators.BinaryOperatorSpacing';

const SQUIZ_CONCAT_SPACING = 'Squiz.Strings.ConcatenationSpacing';

/**
 * The constructs neither spacing sniff registers on, as passing.php line =>
 * the fixture text that line must carry.
 *
 * Every one is written without the spaces the standard demands of a registered
 * operator. That is what makes the fixture discriminating: the line is silent
 * only while the construct really is excluded, and a regression that started
 * registering it — an added T_MATCH_ARROW, a lost function-default, "=&" or
 * by-reference skip inherited from the parent Squiz sniff — turns the line
 * red. Written in
 * the conventional spaced form, the same line would pass either way and guard
 * nothing, so the text is pinned here alongside the count: reformatting the
 * fixture must fail the suite rather than quietly defuse it.
 */
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

/**
 * Both halves of each exclusion are asserted together, and both are needed.
 * The count alone would still pass if someone reformatted a fixture line to
 * the conventional spaced form, which is silent whether the construct is
 * excluded or not; the text pin alone would say nothing about the sniff. The
 * text is read from the fixture's own line rather than searched for across the
 * file, so a construct that moved is a failure rather than a silent match on a
 * neighbour or on this file's comments.
 */
it('stays silent on every documented boundary exclusion', function () use ($spacingFixture): void {
    $source = file(fixturePath('_rulesets/OperatorSpacing', 'passing.php'));
    $counts = violationCountsByLine($spacingFixture('passing.php')->getErrors());

    foreach (BOUNDARY_EXCLUSIONS as $line => $construct) {
        expect($source[$line - 1])->toContain($construct)
            ->and($counts[$line] ?? 0)->toBe(0);
    }
});

/**
 * ignoreSpacingBeforeAssignments="false" makes every "=" carry two checks, so
 * the counts below are what tell the chained-assignment cases apart: a broken
 * link contributes NoSpaceBefore + NoSpaceAfter, and a sniff that stopped
 * reading past the first "=" of a chain — or read only the last — would report
 * 2 on line 13 rather than 4.
 */
it('counts each broken link of a chained assignment separately', function () use ($spacingFixture): void {
    $counts = violationCountsByLine($spacingFixture('failing.php')->getErrors());

    expect($counts[13])->toBe(4)
        ->and($counts[14])->toBe(2);
});

/**
 * An array-literal "=>" is registered where a match-arm one is not, and a
 * unary sign directly after "=" is not an operand boundary the sniff may put a
 * space around — line 11/12 carry the space before "=" already, so only the
 * missing one after it is reported, and never the sign itself.
 */
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
