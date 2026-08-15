<?php

/**
 * The enumerations of sniff codes that more than one suite reads, and the
 * derivations over them.
 *
 * These lived in tests/Contract/SniffContractTest.php while that file was their
 * only reader. tests/Contract/ShippedPackageSmokeTest.php derives its own
 * coverage from SWEPT_SNIFFS and SWEPT_WARNING_SNIFFS, and its expected phpcs
 * exit statuses from AUTOFIXABLE_SNIFFS, so they now have two — and a constant
 * declared in one test file is not reliably defined when another test file's
 * datasets are resolved, because PHPUnit builds every data provider before it
 * has loaded every file. Declaring them here, from the bootstrap, removes the
 * question: they exist before any suite is collected.
 *
 * Loaded by tests/bootstrap.php rather than tests/Pest.php, so that the
 * ordering holds for a plain PHPUnit run too.
 */

declare(strict_types=1);

/**
 * Every sniff wired into rules.xml that reports errors.
 */
const SWEPT_SNIFFS = [
    'CleanCode.Arrays.ArrayAccessors',
    'CleanCode.Arrays.DuplicatedArrayKey',
    'CleanCode.Classes.DisallowStaticMembers',
    'CleanCode.Classes.ExcessiveClassLength',
    'CleanCode.Classes.RequireProperties',
    'CleanCode.Classes.TooManyPublicMethods',
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.CodeSize.TooManyMethods',
    'CleanCode.Conditionals.DisallowElse',
    'CleanCode.Conditionals.DisallowListAssignmentInCondition',
    'CleanCode.Conditionals.DisallowNestedTernary',
    'CleanCode.Conditionals.OneConditionPerLine',
    'CleanCode.ControlStructures.DisallowCountInLoopExpression',
    'CleanCode.ControlStructures.DisallowExitExpression',
    'CleanCode.Controversial.Superglobals',
    'CleanCode.Debug.DisallowDebugFunctions',
    'CleanCode.Functions.DisallowBooleanArgumentFlag',
    'CleanCode.Functions.ExcessiveMethodLength',
    'CleanCode.Functions.ExcessiveParameterList',
    'CleanCode.Livewire.ComponentMarkup',
    'CleanCode.Metrics.CouplingBetweenObjects',
    'CleanCode.Metrics.CyclomaticComplexity',
    'CleanCode.Metrics.ExcessiveClassComplexity',
    'CleanCode.Metrics.ExcessivePublicCount',
    'CleanCode.Metrics.MethodNestingLevel',
    'CleanCode.Metrics.TooManyFields',
    'CleanCode.Naming.BooleanGetMethodName',
    'CleanCode.Naming.LongClassName',
    'CleanCode.Naming.LongVariable',
    'CleanCode.Naming.ShortClassName',
    'CleanCode.Naming.ShortMethodName',
    'CleanCode.Naming.ShortVariable',
    'CleanCode.Operators.BooleanOperatorSpacing',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Operators.OperatorLineBreak',
    'CleanCode.Routes.ApiControllerNamespace',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.CodeAnalysis.AssignmentInCondition',
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

/**
 * Every sniff wired into rules.xml that reports warnings.
 */
const SWEPT_WARNING_SNIFFS = [
    'CleanCode.Arrays.ConvertToCollection',
    'CleanCode.Classes.DisallowConstructorInstantiation',
    'CleanCode.Conditionals.AvoidConditionals',
    'CleanCode.Controllers.ManualModelResolution',
    'CleanCode.Constructors.DisallowCombinedConstructor',
    'CleanCode.Constructors.PrimaryConstructorDelegation',
    'CleanCode.Conditionals.CombinableConditions',
    'CleanCode.Conditionals.MappingArrayCandidate',
    'CleanCode.Controllers.NoCustomActions',
    'CleanCode.Models.DisallowAlwaysOnEagerLoading',
    'CleanCode.Models.RequireLazyLoadingPrevention',
    'CleanCode.Naming.DisallowMagicNumbers',
    'CleanCode.Pattern.AvoidDuplicateCodeBlocks',
    'CleanCode.Testing.NoReflectionAccess',
];

/**
 * The swept sniffs whose failing fixture carries a violation the fixer can
 * resolve. Read for two different things: which sniffs owe an autofixed
 * fixture, and which exit phpcs at 2 rather than 1 on their failing fixture.
 */
const AUTOFIXABLE_SNIFFS = [
    'CleanCode.ClearCode.OneThoughtPerLine',
    'CleanCode.Conditionals.DisallowElse',
    'CleanCode.Conditionals.OneConditionPerLine',
    'CleanCode.Operators.BooleanOperatorSpacing',
    'CleanCode.Operators.NotOperatorSpacing',
    'CleanCode.Strings.MultilineStrings',
    'CleanCode.WhiteSpace.BlankLines',
    'Generic.ControlStructures.InlineControlStructure',
    'SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion',
    'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly',
    'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch',
    'SlevomatCodingStandard.Namespaces.UnusedUses',
];

/**
 * Sniffs held out of tests/Contract/ShippedPackageSmokeTest.php's sweep, each
 * because it already carries that exact end-to-end coverage of its own.
 *
 * An entry here removes a sniff from the sweep, so the bar for adding one is
 * that the sniff is smoke-tested somewhere else in full — both directions
 * through the shipped binary, which is what the sweep gives up on its behalf.
 * That is enforced, not left to this comment: the sweep's own tests re-read this
 * list, fail when an entry stops being swept or stops reaching the shipped
 * binary in either direction, and fail again on any entry the list did not
 * already carry.
 */
const SHIPPED_SMOKE_EXCLUSIONS = [
    // tests/Standards/CyclomaticComplexityTest.php's 'reports the violation end
    // to end through the installed package', which landed with #88 and
    // additionally runs the standard by name, paired with its 'stays silent on
    // its compliant fixture through the installed package'.
    'CleanCode.Metrics.CyclomaticComplexity',
];

/**
 * This package's own sniffs among a swept dataset's entries, minus the ones
 * covered elsewhere.
 *
 * Third-party entries are dropped: rules.xml configures Generic.*,
 * SlevomatCodingStandard.*, Squiz.* and VariableAnalysis.* sniffs this package
 * did not author, and whose own suites cover their own shipping.
 *
 * @param array<int, string> $swept
 *
 * @return array<int, string>
 */
function shippedSmokeSniffs(array $swept): array
{
    $custom = array_filter($swept, static fn (string $code): bool => str_starts_with($code, 'CleanCode.'));

    return array_values(array_diff($custom, SHIPPED_SMOKE_EXCLUSIONS));
}

/**
 * The status phpcs exits with when a sniff reports on its own failing fixture.
 *
 * PHP_CodeSniffer 3.13.6's Runner::runPHPCS() decides this on *fixability*, not
 * on severity: 2 when something reported is fixable, 1 when something reported
 * and none of it is, 0 when nothing did. So the answer is read off the
 * autofixable enumeration above rather than off which of the two swept lists
 * the sniff belongs to.
 */
function expectedFailingStatus(string $sniffCode): int
{
    return in_array($sniffCode, AUTOFIXABLE_SNIFFS, true) === true ? 2 : 1;
}
