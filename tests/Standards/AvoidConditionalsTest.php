<?php

/**
 * Tests the custom CleanCode.Conditionals.AvoidConditionals sniff (Conditionals:
 * Avoid Conditionals, #12). Fixtures live in
 * tests/fixtures/AvoidConditionalsSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: "avoid
 * where possible" is a judgement about whether a better construct was
 * available, which no token walk can make. So there is no autofixed fixture,
 * and the tests below prove every reported violation is non-fixable.
 *
 * The auto-fixable slice of the same standard — the boolean-return if — is
 * carried by SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn
 * as wired into CleanCode/ruleset.xml, and is covered in
 * tests/Rules/AvoidConditionalsRulesTest.php instead.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const AVOID_CONDITIONALS = 'CleanCode.Conditionals.AvoidConditionals';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_CONDITIONALS);
});

/**
 * passing.php deliberately contains no compliant `if` — there is no such
 * thing under this standard. What it does contain is every near-miss token
 * shape the sniff must stay silent on: match and its arms, `??`, `??=`,
 * `?->`, a `?string` nullable type hint, all four loop forms, and catch.
 * Widening register() to any of them reddens this test.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One instance of each violation code, each at its own line and column. The
 * `else` on line 22 of the fixture is deliberately absent from this list: it
 * introduces no new condition, and cyclomatic complexity — the standard's own
 * rationale — does not count it.
 */
it('warns once per conditional construct at its own line and column', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 18, 'column' => 9, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 20, 'column' => 11, 'source' => AVOID_CONDITIONALS . '.ElseIfStatement'],
        ['line' => 29, 'column' => 28, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 34, 'column' => 9, 'source' => AVOID_CONDITIONALS . '.SwitchStatement'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a branch the sniff has no safe rewrite for. getFixableCount() is
 * used rather than violationFixableFlags(), which reads getErrors() only and
 * so would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a token walk that only ever handled the
 * plain braced form. Every entry below is a *different* shape of a construct
 * already covered in failing.php:
 *
 *   21 — brace-less if, so the T_IF carries no scope_opener
 *   25 — the leading if of an `else if` pair
 *   27 — the T_IF half of `else if`, written as two tokens; the T_ELSE that
 *        precedes it on the same line is correctly not reported
 *   32 — alternative-syntax if, whose body is a T_COLON closed by T_ENDIF
 *   34 — the elseif of that same alternative-syntax chain
 *   39 — alternative-syntax switch, closed by T_ENDSWITCH
 *   47 — short ternary `?:`, whose `?` and `:` are adjacent
 *   50 — a nested ternary: two independent `?` tokens on one line
 *   53 — a ternary as a call argument
 *   56 — a ternary inside an arrow function, past the `=>`
 */
it('warns on every variant shape of the four constructs', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 21, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 25, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 27, 'column' => 8, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 32, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.IfStatement'],
        ['line' => 34, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.ElseIfStatement'],
        ['line' => 39, 'column' => 1, 'source' => AVOID_CONDITIONALS . '.SwitchStatement'],
        ['line' => 47, 'column' => 20, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 50, 'column' => 23, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 50, 'column' => 47, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 53, 'column' => 36, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
        ['line' => 56, 'column' => 48, 'source' => AVOID_CONDITIONALS . '.TernaryExpression'],
    ]);
});

/**
 * The `switch` on line 39 of shapes.php has a `case` and a `default`. Only
 * the keyword is reported, once — the remedy is to replace the whole
 * construct, so one warning per arm would be noise, not information.
 */
it('reports a switch once, not once per case', function (): void {
    $file = analyzeFixture(AVOID_CONDITIONALS, 'shapes.php');

    $switchWarnings = array_filter(
        warningTuples($file),
        static fn (array $violation): bool => $violation['source'] === AVOID_CONDITIONALS . '.SwitchStatement'
    );

    expect($switchWarnings)->toHaveCount(1);
});
