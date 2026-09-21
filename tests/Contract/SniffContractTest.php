<?php

/**
 * The floor every sniff wired into CleanCode/ruleset.xml has to clear, swept across the
 * enumerations in tests/Sniffs.php rather than restated here.
 */

declare(strict_types=1);

dataset('every swept sniff', array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS));

dataset('error-reporting sniffs', SWEPT_SNIFFS);

dataset('warning-reporting sniffs', SWEPT_WARNING_SNIFFS);

dataset('autofixable sniffs', AUTOFIXABLE_SNIFFS);

/**
 * The autofixable sniffs whose fixer resolves every violation its failing
 * fixture reports, so the fixed output doubles as a compliant fixture. Every
 * autofixable sniff left out of this list still reports on its own
 * autofixed.php — CleanCode.Conditionals.OneConditionPerLine is the worked
 * example, its one survivor a split condition wrapping a comment that the
 * fixer withholds because rejoining it would have to decide where the comment
 * goes, pinned in tests/Standards/OneConditionPerLineTest.php.
 *
 * A constant rather than an inline literal only so the alphabetical-order test
 * below can read it, and declared here rather than in tests/Sniffs.php because
 * this file is its only reader — the cross-file load-ordering hazard that put
 * the other enumerations in the bootstrap does not arise within one file.
 */
const TOTAL_FIXER_SNIFFS = [
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Indentation.LogicalGroupings',
    'CleanCode.Operators.BinaryOperatorSpacing',
    'CleanCode.Operators.BooleanOperatorSpacing',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'CleanCode.WhiteSpace.MultiLineStatementIndent',
    'CleanCode.WhiteSpace.PassiveOperatorSpacing',
    'Generic.ControlStructures.InlineControlStructure',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
];

dataset('sniffs whose fixer resolves every violation', TOTAL_FIXER_SNIFFS);

/**
 * Every sniff list is kept in strict alphabetical order, so an added sniff has
 * exactly one place to go and a reader can scan for one. Nothing else enforces
 * that: the lists are data, and every assertion over them holds whatever order
 * they are in, so a misfiled entry rides through a fully green suite. This is
 * the only thing that catches it.
 */
it('keeps every sniff list in alphabetical order', function (array $sniffCodes): void {
    $sorted = $sniffCodes;
    sort($sorted, SORT_STRING);

    expect($sniffCodes)->toBe($sorted);
})->with([
    'error-reporting' => [SWEPT_SNIFFS],
    'warning-reporting' => [SWEPT_WARNING_SNIFFS],
    'autofixable' => [AUTOFIXABLE_SNIFFS],
    'total fixer' => [TOTAL_FIXER_SNIFFS],
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

/**
 * Every fixer's output has to be source PHP_CodeSniffer can still read.
 *
 * `php -l` is not that bar and cannot stand in for it: PHP accepts shapes the
 * PHPCS tokenizer does not classify, and an unclassified token does not fail
 * loudly — it silently swallows the source that follows it into one bogus
 * token, so the *next* pass over the fixed file reads live code as string body.
 * That is invisible to the byte-comparison above, whose expected fixture can be
 * committed already corrupted, and to any assertion that only reads the fixed
 * string's content.
 *
 * Swept across every autofixable sniff rather than pinned per-sniff, because
 * this failure mode has recurred a site at a time — each round fixing the one
 * place named and leaving the class open. The source fixture is asserted clean
 * in the same breath, so a fixture that was already unreadable cannot make this
 * pass vacuously.
 */
it('emits source the tokenizer can still read', function (string $sniffCode): void {
    $fixed = autofixedContents(analyzeFixture($sniffCode, 'failing.php'));
    $source = (string) file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'failing.php'));

    expect(unclassifiedTokens($source))->toBe([])
        ->and(unclassifiedTokens($fixed))->toBe([]);
})->with('autofixable sniffs');
