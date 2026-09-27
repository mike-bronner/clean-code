<?php

declare(strict_types=1);

dataset('every swept sniff', array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS));

dataset('error-reporting sniffs', SWEPT_SNIFFS);

dataset('warning-reporting sniffs', SWEPT_WARNING_SNIFFS);

dataset('autofixable sniffs', AUTOFIXABLE_SNIFFS);

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

it('warns on the failing fixture', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'failing.php')->getWarnings())->not->toBeEmpty();
})->with('warning-reporting sniffs');

it('autofixes the failing fixture into the autofixed fixture', function (string $sniffCode): void {
    $file = analyzeFixture($sniffCode, 'failing.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'autofixed.php')));
})->with('autofixable sniffs');

it('is idempotent over its own fixed output', function (string $sniffCode): void {
    expect(autofixedContents(analyzeFixture($sniffCode, 'autofixed.php')))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'autofixed.php')));
})->with('autofixable sniffs');

it('leaves the autofixed fixture clean', function (string $sniffCode): void {
    expect(analyzeFixture($sniffCode, 'autofixed.php')->getErrors())->toBeEmpty();
})->with('sniffs whose fixer resolves every violation');

it('emits source the tokenizer can still read', function (string $sniffCode): void {
    $fixed = autofixedContents(analyzeFixture($sniffCode, 'failing.php'));
    $source = (string) file_get_contents(fixturePath(sniffFixtureDirectory($sniffCode), 'failing.php'));

    expect(unclassifiedTokens($source))->toBe([])
        ->and(unclassifiedTokens($fixed))->toBe([]);
})->with('autofixable sniffs');
