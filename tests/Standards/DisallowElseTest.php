<?php

/**
 * Tests the custom CleanCode.Conditionals.DisallowElse sniff, which replaces
 * PHPMD's CleanCode/ElseExpression rule (#77). Fixtures live in
 * tests/fixtures/DisallowElseSniff/ and carry the two floor files only —
 * the sniff is detection-only, so there is no autofixed.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 *
 * Every parity claim below was checked against a live PHPMD 2.15.0 run over
 * these same two fixtures; docs/phpmd/cleancode-elseexpression.md records the
 * mapping and both divergences.
 */

declare(strict_types=1);

const DISALLOW_ELSE = 'CleanCode.Conditionals.DisallowElse';

const DISALLOW_ELSE_FOUND = DISALLOW_ELSE . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_ELSE);
});

/**
 * passing.php is deliberately discriminating: alongside the compliant early
 * exit, guard clause, and ternary, it carries every near-miss the sniff must
 * stay silent on — `elseif`, the two-word `else if`, the alternative-syntax
 * `elseif`, and the member shapes PHP lets the reserved word `else` name: a
 * method, class-constant, and enum-case declaration, plus a reference to each
 * through `->`, `self::`, and `Enum::`. PHPMD 2.15.0 reports nothing on this
 * fixture either.
 *
 * The member shapes need no guard in the sniff: PHPCS rewrites `else` to
 * T_STRING in each of those positions before a sniff runs, so the sniff never
 * registers on one and removing the fixture lines changes no result today.
 * They stay because that rewrite is a tokenizer detail this sniff leans on,
 * and pinning it turns a change to it into a test failure here rather than a
 * false positive in consumers' code.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One entry per `else` keyword in failing.php, at the keyword's own line and
 * column. This list is the sniff's whole detection contract — it is exact, so
 * a missed else and an extra report both fail here, and no separate silence
 * or divergence test could add anything it does not already pin.
 *
 * Reading down: a file-scope else (15), a plain if/else (25), an else after
 * an early return (36), an if/elseif/else chain (47), an if/`else if`/else
 * chain (60), a nested if/else inside an if/else (72 inner, 75 outer), a
 * braceless single-statement else (86), an alternative-syntax `else:` (96),
 * and an else inside a closure (108).
 *
 * Two things this list pins by *omission*, both deliberate:
 *
 *  - The `elseif` on line 45 and the two-word `else if` on line 56 are absent.
 *    PHPMD's rule fires on the else scope — the third child of an if/elseif
 *    node — so an if/elseif chain with no closing else produces nothing at
 *    all. Registering T_ELSEIF, or dropping the `else if` lookahead, adds a
 *    line here. Both keywords sit inside a fixture the sniff reports ten
 *    times, so neither can hide as ambient silence.
 *  - Lines 15 and 86 are the two shapes where this sniff is *stricter* than
 *    the rule it replaces. PHPMD 2.15.0 reports eight of these ten: it misses
 *    the file-scope else, because ElseExpression is MethodAware/FunctionAware
 *    and never visits code outside a function, and it misses the braceless
 *    else, because it matches a ScopeStatement, which PDepend only builds for
 *    a braced body. Both are the same avoidable branch the rule targets, so
 *    both are reported rather than suppressed for parity. Dropping either
 *    line from this list contradicts
 *    docs/phpmd/cleancode-elseexpression.md and rules.xml.
 *
 * The PHPMD side of both claims was measured against a live PHPMD 2.15.0 run
 * over this fixture. It cannot be asserted here — phpmd is not a dependency
 * of this package, which is the entire point of replacing the rule.
 */
it('flags every else at its own line and column', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 15, 'column' => 3, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 25, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 36, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 47, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 60, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 72, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 75, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 86, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 96, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 108, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
    ]);
});

/**
 * Detection-only, matching PHPMD. Rewriting an else branch into an early exit
 * means moving statements and inverting a condition — safe only in the narrow
 * shapes where one branch already ends in a jump, which is the subset
 * SlevomatCodingStandard.ControlStructures.EarlyExit fixes and a strict
 * minority of what this sniff reports. Asserting the flags rather than the
 * absence of an autofixed.php means a fixer added later cannot slip in
 * unnoticed.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationFixableFlags($file))->toBe(array_fill(0, 10, false));
});

/**
 * The half of the severity contract the tuple assertion cannot see: it reads
 * getErrors() only, so it would hold just as well if the sniff *also* raised
 * a warning per else. PHPMD fails a run on an ElseExpression violation, so a
 * warning-level report would leave phpcs exiting 0 and phpmd would still have
 * to run separately for this rule.
 */
it('raises no warnings alongside the errors', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect($file->getWarnings())->toBe([]);
});
