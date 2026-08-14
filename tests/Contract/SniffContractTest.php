<?php

/**
 * The floor every sniff wired into rules.xml has to clear, swept across the
 * enumerations in tests/Sniffs.php rather than restated here.
 */

declare(strict_types=1);

dataset('every swept sniff', array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS));

dataset('error-reporting sniffs', SWEPT_SNIFFS);

dataset('warning-reporting sniffs', SWEPT_WARNING_SNIFFS);

dataset('autofixable sniffs', AUTOFIXABLE_SNIFFS);

dataset('sniffs whose fixer resolves every violation', [
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'CleanCode.WhiteSpace.MultiLineStatementIndent',
    'Generic.ControlStructures.InlineControlStructure',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
]);

it('resolves every sniff through the master ruleset', function (string $sniffCode): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey($sniffCode);
})->with('every swept sniff');

it('leaves the passing fixture untouched', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'passing.php')->getErrors())->toBeEmpty();
})->with('every swept sniff');

it('raises no warnings on the passing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'passing.php')->getWarnings())->toBeEmpty();
})->with('every swept sniff');

it('flags the failing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'failing.php')->getErrors())->not->toBeEmpty();
})->with('error-reporting sniffs');

/**
 * The warning-level half of the same floor. Asserted against getWarnings()
 * rather than getErrors(), because these sniffs never raise an error and the
 * error assertion above would hold just as well against a sniff that had
 * fallen silent altogether.
 */
it('warns on the failing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'failing.php')->getWarnings())->not->toBeEmpty();
})->with('warning-reporting sniffs');

it('autofixes the failing fixture into the autofixed fixture', function (string $sniffCode): void {
    $file = analyzeFixture($sniffCode, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'autofixed.php')));
})->with('autofixable sniffs');

/**
 * Re-running the fixer over its own output must change nothing. A fixer that
 * merely relocated a violation, or that oscillated between two spellings,
 * passes the byte-comparison above but fails here.
 */
it('is idempotent over its own fixed output', function (string $sniffCode): void {
    expect(autofixedContents(analyzeFixture($sniffCode, 'autofixed.php')))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'autofixed.php')));
})->with('autofixable sniffs');

/**
 * Where the fixer is total, its output doubles as a passing fixture: nothing
 * the sniff can fix is left behind.
 */
it('leaves the autofixed fixture clean', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'autofixed.php')->getErrors())->toBeEmpty();
})->with('sniffs whose fixer resolves every violation');
