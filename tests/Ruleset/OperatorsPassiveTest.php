<?php

/**
 * Operators: Passive (#64) as wired into CleanCode/ruleset.xml — the whole standard rather
 * than the one custom sniff that anchors it.
 *
 * Four sniffs carry it: CleanCode.WhiteSpace.PassiveOperatorSpacing for
 * identity, negation, error control and execution, plus three third-party
 * sniffs configured in CleanCode/ruleset.xml for increment/decrement, `->` and `[]`.
 * Fixtures live in tests/fixtures/_rulesets/OperatorsPassive/ because no single
 * sniff owns the standard.
 *
 * The last test here is the one that matters most. Every previous round of this
 * standard's review found the same defect: a fixer in the master ruleset that
 * re-inserted a space this standard's fixer had just removed, so
 * `phpcbf --standard=CleanCode/ruleset.xml` never settled and gave up on the whole file
 * (exit 2). It is a whole-ruleset failure by construction — the isolated
 * assertions above cannot see it — so it is asserted against the real phpcbf
 * binary over the real master ruleset.
 */

declare(strict_types=1);

const OPERATORS_PASSIVE_SNIFFS = [
    'CleanCode.WhiteSpace.PassiveOperatorSpacing',
    'Generic.WhiteSpace.IncrementDecrementSpacing',
    'Squiz.WhiteSpace.ObjectOperatorSpacing',
    'Squiz.Arrays.ArrayBracketSpacing',
];

it('wires every sniff the standard needs into the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    foreach (OPERATORS_PASSIVE_SNIFFS as $sniffCode) {
        expect($ruleset->sniffCodes)->toHaveKey($sniffCode);
    }
});

it('reports nothing on the compliant fixture', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every operator in the standard, flagged, and by the sniff that owns it. The
 * assertion is per line and per source, so a rule silently dropped from
 * CleanCode/ruleset.xml shows up as a missing line rather than a smaller total.
 */
it('flags every passive operator through the sniff that owns it', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'failing.php');
    $sources = violationSourcesByLine($file->getErrors());

    expect(array_keys($sources))->toBe([9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22])
        ->and($sources[9])->toBe(['CleanCode.WhiteSpace.PassiveOperatorSpacing.Identity'])
        ->and($sources[11])->toBe(['Generic.WhiteSpace.IncrementDecrementSpacing.SpaceAfterIncrement'])
        ->and($sources[18])->toBe(['Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket'])
        ->and($sources[19])->toBe([
            'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
            'Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket',
        ])
        ->and($sources[20])->toBe([
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
        ])
        ->and($sources[21])->toBe([
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.Before',
            'Squiz.WhiteSpace.ObjectOperatorSpacing.After',
        ]);
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeRulesetFixture(OPERATORS_PASSIVE_SNIFFS, 'OperatorsPassive', 'failing.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/../fixtures/_rulesets/OperatorsPassive/autofixed.php')
    );
});

/**
 * The regression guard for the whole oscillation class.
 *
 * `convergence.php` carries one line per context in which a `+`/`-` sign sits
 * where the binary-operator sniff might read it as binary: after `@`, after a
 * semicolon, after `<?php`, after `<?=`, and — the control — a genuinely binary
 * sign after a postfix increment, which must keep its spaces. Under the real
 * master ruleset, phpcbf must reach a fixed point on all of them.
 *
 * Exit status 2 is phpcbf's "could not fix" and is exactly the symptom every
 * earlier round of this standard produced; 0 and 1 are both success (nothing to
 * fix / fixes written). The second pass asserts the result is stable rather
 * than merely reachable.
 */
it('converges under the real phpcbf over the whole master ruleset', function (): void {
    $staged = stageFixtureOutsideTests(
        __DIR__ . '/../fixtures/_rulesets/OperatorsPassive/convergence.php'
    );

    $command = sprintf(
        '%s --standard=%s --no-cache %s',
        escapeshellarg(__DIR__ . '/../../vendor/bin/phpcbf'),
        escapeshellarg('CleanCode'),
        escapeshellarg($staged)
    );

    [, , $status] = runOutsidePackage($command);

    expect($status)->not->toBe(2);

    $fixed = file_get_contents($staged);

    expect($fixed)->toBe(file_get_contents(
        __DIR__ . '/../fixtures/_rulesets/OperatorsPassive/convergence.fixed.php'
    ));

    // A second pass must change nothing: a pair of fixers that merely take
    // turns would still reach a "fixed" file on pass one.
    [, , $secondStatus] = runOutsidePackage($command);

    expect($secondStatus)->toBe(0)
        ->and(file_get_contents($staged))->toBe($fixed);
});
