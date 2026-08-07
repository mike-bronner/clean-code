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
 * passing.php and failing.php are the floor: every sniff in this sweep carries
 * both. The only axis on which coverage varies is the fixer —
 *
 *   - Autofixable sniffs carry all three fixtures.
 *   - Detection-only sniffs (ArrayAccessors, OperatorLineBreak,
 *     DisallowStaticMembers, LineLength, Eval, VariableAnalysis) carry
 *     passing.php and failing.php but no autofixed.php, because there is no
 *     safe mechanical rewrite.
 *
 * — which is why the contract is expressed as separate datasets rather than one
 * list. An autofixed.php is never a substitute for a passing.php: it is the
 * fixer's own output, so asserting a sniff is silent on it tests the fixer
 * twice and the compliant form never. CleanCode.Conditionals.OneConditionPerLine
 * makes that concrete — its autofixed.php deliberately retains a non-fixable
 * violation, so it could not stand in for a passing fixture even in principle.
 *
 * Two sniffs are deliberately absent from every dataset:
 *
 *   - CleanCode.Models.DisallowExternalPersistenceCalls — rules.xml scopes it
 *     out of test paths, so processing its fixtures where they live reports
 *     nothing whatever the sniff does. It is covered in
 *     tests/Standards/DisallowExternalPersistenceCallsTest.php, which stages
 *     each fixture outside the repository first.
 *   - The naming casing conventions — a composite standard carried by three
 *     sniffs at once, so it has no per-sniff fixture directory to sweep. It is
 *     covered in tests/Ruleset/CasingConventionsRulesetTest.php.
 */

declare(strict_types=1);

/**
 * Every sniff the sweep covers. passing.php and failing.php are the contract's
 * floor, so both datasets are fed from this one list rather than being written
 * out twice: a sniff cannot be added to one and forgotten in the other.
 */
const SWEPT_SNIFFS = [
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
    'Squiz.PHP.Eval',
    'VariableAnalysis.CodeAnalysis.VariableAnalysis',
];

dataset('sniffs with a passing fixture', SWEPT_SNIFFS);

dataset('sniffs with a failing fixture', SWEPT_SNIFFS);

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
