<?php

declare(strict_types=1);

const SWEPT_SNIFFS = [
    'CleanCode.Arrays.ArrayAccessors',
    'CleanCode.Arrays.DuplicatedArrayKey',
    'CleanCode.Classes.DisallowStaticMembers',
    'CleanCode.Classes.ExcessiveClassLength',
    'CleanCode.Classes.TooManyPublicMethods',
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.CodeSize.TooManyMethods',
    'CleanCode.Conditionals.DisallowElse',
    'CleanCode.Conditionals.OneConditionPerLine',
    'CleanCode.ControlStructures.DisallowCountInLoopExpression',
    'CleanCode.ControlStructures.DisallowExitExpression',
    'CleanCode.Controversial.Superglobals',
    'CleanCode.Debug.DisallowDebugFunctions',
    'CleanCode.Functions.DisallowBooleanArgumentFlag',
    'CleanCode.Functions.ExcessiveMethodLength',
    'CleanCode.Functions.ExcessiveParameterList',
    'CleanCode.Metrics.CouplingBetweenObjects',
    'CleanCode.Metrics.CyclomaticComplexity',
    'CleanCode.Metrics.ExcessiveClassComplexity',
    'CleanCode.Metrics.ExcessivePublicCount',
    'CleanCode.Metrics.TooManyFields',
    'CleanCode.Naming.BooleanGetMethodName',
    'CleanCode.Naming.LongClassName',
    'CleanCode.Naming.ShortClassName',
    'CleanCode.Naming.ShortMethodName',
    'CleanCode.Naming.ShortVariable',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Operators.OperatorLineBreak',
    'CleanCode.Routes.ApiControllerNamespace',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.ControlStructures.InlineControlStructure',
    'Generic.Files.LineLength',
    'Generic.NamingConventions.ConstructorName',
    'Generic.PHP.DiscourageGoto',
    'Generic.PHP.NoSilencedErrors',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
    'Squiz.PHP.Eval',
    'VariableAnalysis.CodeAnalysis.VariableAnalysis',
];

const SWEPT_WARNING_SNIFFS = [
    'CleanCode.Arrays.ConvertToCollection',
    'CleanCode.Classes.DisallowConstructorInstantiation',
    'CleanCode.Conditionals.AvoidConditionals',
    'CleanCode.Conditionals.MappingArrayCandidate',
    'CleanCode.Controllers.NoCustomActions',
    'CleanCode.Models.DisallowAlwaysOnEagerLoading',
    'CleanCode.Models.RequireLazyLoadingPrevention',
    'CleanCode.Naming.DisallowMagicNumbers',
    'CleanCode.Pattern.AvoidDuplicateCodeBlocks',
    'CleanCode.Testing.NoReflectionAccess',
];

dataset('every swept sniff', array_merge(SWEPT_SNIFFS, SWEPT_WARNING_SNIFFS));

dataset('error-reporting sniffs', SWEPT_SNIFFS);

dataset('warning-reporting sniffs', SWEPT_WARNING_SNIFFS);

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
