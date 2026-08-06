<?php

/**
 * The three-fixture contract every per-sniff fixture directory follows:
 *
 *   passing.php    code the sniff must leave alone
 *   failing.php    code the sniff must flag
 *   autofixed.php  phpcbf's output for failing.php (fixable sniffs only)
 *
 * This is the generic sweep. It proves each sniff is wired into rules.xml and
 * broadly does the right thing on each fixture; the exact line, column, and
 * violation-source assertions live in the per-sniff files under
 * tests/Standards, tests/Rules, and tests/Ruleset.
 *
 * Coverage is uneven by design, so the contract is expressed as three datasets
 * rather than one — a sniff appears in the datasets its fixtures support:
 *
 *   - Detection-only sniffs (ArrayAccessors, OperatorLineBreak,
 *     DisallowStaticMembers, LineLength) have no autofixed.php, because there
 *     is no safe mechanical rewrite.
 *   - Sniffs whose compliant form is just ordinary code (BlankLines,
 *     OneThoughtPerLine, InlineControlStructure, the two Exceptions rules, …)
 *     carry no passing.php; their autofixed.php serves that role, and the
 *     idempotence test below asserts it.
 *
 * CleanCode.Models.DisallowExternalPersistenceCalls is deliberately absent from
 * every dataset. rules.xml scopes it out of test paths, so processing its
 * fixtures where they live reports nothing whatever the sniff does; it is
 * covered in tests/Standards/DisallowExternalPersistenceCallsTest.php, which
 * stages each fixture outside the repository first.
 */

declare(strict_types=1);

dataset('sniffs with a passing fixture', [
    'CleanCode.Arrays.ArrayAccessors',
    'CleanCode.Classes.DisallowStaticMembers',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Operators.OperatorLineBreak',
    'CleanCode.Strings.MultilineStrings',
    'Generic.Files.LineLength',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
]);

dataset('sniffs with a failing fixture', [
    'CleanCode.Arrays.ArrayAccessors',
    'CleanCode.Classes.DisallowStaticMembers',
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Conditionals.OneConditionPerLine',
    'CleanCode.Debug.DisallowDebugFunctions',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Operators.OperatorLineBreak',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.ControlStructures.InlineControlStructure',
    'Generic.Files.LineLength',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
]);

dataset('autofixable sniffs', [
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Conditionals.OneConditionPerLine',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.ControlStructures.InlineControlStructure',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
]);

/**
 * Every autofixable sniff except CleanCode.Conditionals.OneConditionPerLine,
 * whose failing.php deliberately includes a split single condition wrapping a
 * comment: that violation is reported but withheld from the fixer, because
 * rejoining the condition would have to decide where the comment goes. Its
 * autofixed.php therefore legitimately retains one non-fixable error, pinned
 * exactly in tests/Standards/OneConditionPerLineTest.php.
 */
dataset('sniffs whose fixer resolves every violation', [
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.ControlStructures.InlineControlStructure',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
]);

it('resolves every sniff through the master ruleset', function (string $sniffCode): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey($sniffCode);
})->with('sniffs with a failing fixture');

it('leaves the passing fixture untouched', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'passing.php')->getErrors())->toBeEmpty();
})->with('sniffs with a passing fixture');

it('raises no warnings on the passing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'passing.php')->getWarnings())->toBeEmpty();
})->with('sniffs with a passing fixture');

it('flags the failing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'failing.php')->getErrors())->not->toBeEmpty();
})->with('sniffs with a failing fixture');

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
