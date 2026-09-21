<?php

/**
 * Tests the custom CleanCode.Operators.BooleanOperatorSpacing sniff
 * (Operators: Active, #62). Fixtures live in
 * tests/fixtures/BooleanOperatorSpacingSniff/ and follow the three-fixture
 * contract: passing.php is clean, failing.php carries all four violation codes
 * for each of the five operators, and autofixed.php is phpcbf's output for
 * failing.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml. The assertions that deliberately do
 * *not* narrow are the disjointness tests at the bottom, which are about how
 * this sniff relates to every other sniff that touches its tokens.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Operators\BooleanOperatorSpacingSniff;
use MikeBronner\CleanCode\Sniffs\Operators\NotOperatorSpacingSniff;
use PHP_CodeSniffer\Standards\PSR12\Sniffs\Operators\OperatorSpacingSniff as Psr12OperatorSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Strings\ConcatenationSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\LogicalOperatorSpacingSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;

const BOOLEAN_OPERATOR_SPACING = 'CleanCode.Operators.BooleanOperatorSpacing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_OPERATOR_SPACING);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exhaustive map. Every one of the five operators carries all four defect
 * shapes, so each violation code is pinned against each operator rather than
 * only against whichever one happens to be listed first: missing space before
 * (NoSpaceBefore), missing space after (NoSpaceAfter), missing on both sides
 * (both codes on one line), and over-padding on both sides (SpacingBefore +
 * SpacingAfter).
 *
 * The word operators `and`/`or`/`xor` are the sniff's actual reason to exist —
 * Squiz.WhiteSpace.OperatorSpacing never registered them — so they are pinned
 * here on equal footing with `&&` and `||`, not treated as an afterthought.
 *
 * Every column is the operator token's own position, so the two codes a
 * both-sides defect raises land on one column rather than one per side.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 17, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 18, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 18, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 19, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 19, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 21, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 22, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 23, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 23, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 24, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 24, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 26, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 27, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 28, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 28, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 29, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 29, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 31, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 32, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 33, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 33, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 34, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 34, 'column' => 21, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],

        ['line' => 36, 'column' => 24, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 37, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 38, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceBefore'],
        ['line' => 38, 'column' => 23, 'source' => BOOLEAN_OPERATOR_SPACING . '.NoSpaceAfter'],
        ['line' => 39, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingBefore'],
        ['line' => 39, 'column' => 22, 'source' => BOOLEAN_OPERATOR_SPACING . '.SpacingAfter'],
    ]);
});

it('marks every violation fixable', function (): void {
    $file = analyzeFixture(BOOLEAN_OPERATOR_SPACING, 'failing.php');

    expect($file->getErrorCount())->toBe(30)
        ->and($file->getFixableCount())->toBe($file->getErrorCount());
});

/**
 * Pins the narrowing at its source rather than through its symptoms.
 *
 * The sniff exists to cover the five boolean operators and nothing else: the
 * rest of the "active" operator list is already owned by three other sniffs,
 * and registering any of their tokens here reports those violations twice
 * (see the class docblock and OperatorRulesIntegrationTest). The integration
 * test catches the duplication end-to-end, but only for the operators its one
 * fixture happens to use; this asserts the disjointness directly, for every
 * token each of the four sniffs registers.
 */
it('registers exactly the five boolean operators', function (): void {
    expect((new BooleanOperatorSpacingSniff())->register())
        ->toBe([
            T_BOOLEAN_AND => T_BOOLEAN_AND,
            T_BOOLEAN_OR => T_BOOLEAN_OR,
            T_LOGICAL_AND => T_LOGICAL_AND,
            T_LOGICAL_OR => T_LOGICAL_OR,
            T_LOGICAL_XOR => T_LOGICAL_XOR,
        ]);
});

it('shares no registered token with the sniffs that own the rest of the standard', function (
    string $sniffClass
): void {
    $ours = (new BooleanOperatorSpacingSniff())->register();
    $theirs = (new $sniffClass())->register();

    expect(array_intersect(array_values($ours), array_values($theirs)))->toBe([]);
})->with([
    'assignment operators — Squiz.WhiteSpace.OperatorSpacing' => OperatorSpacingSniff::class,
    'concatenation — Squiz.Strings.ConcatenationSpacing' => ConcatenationSpacingSniff::class,
    'logical not — CleanCode.Operators.NotOperatorSpacing' => NotOperatorSpacingSniff::class,
]);

/**
 * The other half of the same property, for the sniffs that are *not* disjoint.
 *
 * Two spacing sniffs in the tree register these exact five tokens on top of
 * whatever else they cover. Neither is narrowable into disjointness — the
 * overlap is total — so the only thing keeping one diagnostic per violation is
 * that neither reaches the master ruleset:
 *
 * - PSR12.Operators.OperatorSpacing adds Tokens::$booleanOperators to the Squiz
 *   base set it inherits, and is held out by the blanket
 *   <exclude name="PSR12.Operators.OperatorSpacing"/> in CleanCode/ruleset.xml. Narrowing
 *   that exclude to the assignment/concatenation codes it was originally
 *   written for — which its own comment describes — would silently double every
 *   violation this sniff reports.
 * - Squiz.WhiteSpace.LogicalOperatorSpacing enforces the same "exactly one
 *   space" rule on the same five tokens. Nothing excludes it; CleanCode/ruleset.xml simply
 *   never refs it, individually or via the whole Squiz standard. Adding either
 *   would double them the same way.
 *
 * Each case asserts both halves, because either one going stale silently
 * disarms the guard: that the overlap is still total (an upstream register()
 * that stops covering these tokens makes the exclusion pointless, not
 * load-bearing), and that the sniff is still absent from the master ruleset.
 */
it('keeps the spacing sniffs that do overlap out of the master ruleset', function (
    string $sniffCode,
    string $sniffClass
): void {
    $ours = array_values((new BooleanOperatorSpacingSniff())->register());
    $theirs = array_values((new $sniffClass())->register());

    expect(array_values(array_intersect($ours, $theirs)))->toBe($ours);

    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey($sniffCode);
})->with([
    'PSR12.Operators.OperatorSpacing — excluded' => [
        'PSR12.Operators.OperatorSpacing',
        Psr12OperatorSpacingSniff::class,
    ],
    'Squiz.WhiteSpace.LogicalOperatorSpacing — never referenced' => [
        'Squiz.WhiteSpace.LogicalOperatorSpacing',
        LogicalOperatorSpacingSniff::class,
    ],
]);

/**
 * The catch-all, and the only assertion here that survives a sniff nobody
 * thought to enumerate above.
 *
 * Both lists in this file are hardcoded, so both go stale the moment a new
 * sniff lands. This one reads the master ruleset instead and names every active
 * sniff that registers any of the five tokens, so anything new — third-party
 * ref, or a CleanCode sniff of our own — has to be classified deliberately
 * before the suite goes green again.
 *
 * The two entries besides this sniff are intentional co-registrations, not
 * overlaps: neither reports on spacing, so neither can duplicate a diagnostic.
 * CleanCode.Operators.OperatorLineBreak owns where a wrapped boolean expression
 * breaks (which is why $ignoreNewlines is set here), and
 * Generic.PHP.LowerCaseKeyword owns the casing of the word forms.
 */
it('shares these tokens in the master ruleset only with sniffs reporting another concern', function (): void {
    [, $ruleset] = buildRuleset();

    $ours = array_values((new BooleanOperatorSpacingSniff())->register());

    $sharing = array_keys(array_filter(
        $ruleset->sniffCodes,
        static fn (string $class): bool => array_intersect(
            array_values((array) $ruleset->sniffs[$class]->register()),
            $ours
        ) !== []
    ));

    sort($sharing);

    expect($sharing)->toBe([
        BOOLEAN_OPERATOR_SPACING,
        'CleanCode.Operators.OperatorLineBreak',
        'Generic.PHP.LowerCaseKeyword',
    ]);
});
