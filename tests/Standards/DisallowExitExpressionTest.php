<?php

/**
 * Tests the custom CleanCode.ControlStructures.DisallowExitExpression sniff,
 * which replicates PHPMD's Design/ExitExpression rule
 * (docs/phpmd/design-exitexpression.md).
 *
 * Every line asserted below was measured against a live PHPMD 2.15.0 run over
 * the same fixtures, not read off PHPMD's documentation: `phpmd <fixture> text
 * design` reports the identical fifteen lines of failing.php and stays silent
 * on passing.php. divergences.php holds the two shapes where the two tools
 * disagree, so the parity claim covers the whole of both floor fixtures rather
 * than most of them.
 *
 * The rule is detection-only — replacing a termination with a return value or
 * an exception is a judgement about what the code was meant to do — so there is
 * no autofixed fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const DISALLOW_EXIT_EXPRESSION = 'CleanCode.ControlStructures.DisallowExitExpression';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_EXIT_EXPRESSION);
});

/**
 * passing.php is the sniff's whole silence contract, and each block in it is a
 * separate verdict rather than merely the absence of an `exit`:
 *
 * - File-scope `exit`/`die`, including inside a top-level `if` or `foreach`.
 *   PHPMD reports an exit expression only inside a function or method, because
 *   its own remedy is to relocate the exit to a startup script — and a startup
 *   script's exit lives at file scope. Flagging it would forbid the fix.
 * - A closure and an arrow function written at file scope, which belong to no
 *   function or method. PHPMD is silent on both; so is this sniff.
 * - A method named `exit` or `die` (legal since PHP 7.0), declared and called,
 *   at file scope and again from inside a method. These are silent because
 *   PHPCS treats `exit`/`die` as context-sensitive keywords and demotes them to
 *   T_STRING after `function`, `::`, and the object operators — so the sniff
 *   never sees them at all. That is the reason no guard for them exists in the
 *   sniff, and this fixture is what pins the tokenizer behaviour the omission
 *   depends on: were PHPCS to stop demoting them, these lines would flag.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exact position of every violation in failing.php. Asserting the column
 * as well as the line matters here: the violation is reported on the `exit`
 * token itself, not on the enclosing function, so a rewrite that reported the
 * declaration instead would keep the right line for `bareExit()` and get every
 * nested case wrong.
 */
it('flags every exit expression at its own position', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    $positions = array_map(
        static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
        violationTuples($file)
    );

    expect($positions)->toBe([
        [5, 5],
        [10, 5],
        [15, 5],
        [20, 5],
        [25, 5],
        [30, 5],
        [35, 5],
        [41, 9],
        [47, 22],
        [54, 9],
        [60, 13],
        [68, 32],
        [76, 17],
        [86, 9],
        [96, 9],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * Every violation carries the one message code, so a consuming ruleset can
 * silence the rule with a single `<exclude>`.
 */
it('reports every violation under one message code', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    $sources = array_unique(array_column(violationTuples($file), 'source'));

    expect($sources)->toBe([DISALLOW_EXIT_EXPRESSION . '.Found']);
});

/**
 * The construct is matched case-insensitively — PHP accepts `EXIT` and `Die` —
 * but the message quotes the source's own spelling rather than a normalised
 * one, so the diagnostic points at what the author actually wrote.
 */
it('quotes the construct as it was written', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');
    $errors = $file->getErrors();

    expect($errors[30][5][0]['message'])->toStartWith('Exit expression EXIT ')
        ->and($errors[35][5][0]['message'])->toStartWith('Exit expression Die ')
        ->and($errors[5][5][0]['message'])->toStartWith('Exit expression exit ')
        ->and($errors[20][5][0]['message'])->toStartWith('Exit expression die ');
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The two shapes where this sniff is deliberately stricter than PHPMD, kept
 * apart from failing.php so that fixture can claim exact parity.
 *
 * PHPMD 2.15.0 reports nothing on any of these four lines: PDepend does not
 * model a method of a file-scope anonymous class as a method, and it does not
 * recognise the fully qualified `\exit` / `\die` spelling. Both are real exit
 * expressions inside a method or function, so this sniff reports them — the
 * same posture CleanCode/ruleset.xml already takes for VariableAnalysis, where extra
 * reports that are true defects are kept rather than suppressed for parity.
 *
 * The anonymous-class half is specifically a parser blind spot, not a PHPMD
 * design decision: the identical anonymous class written inside a named method
 * *is* reported by both tools, and failing.php line 96 pins that.
 */
it('reports the shapes PHPMD misses', function (): void {
    $file = analyzeFixture(DISALLOW_EXIT_EXPRESSION, 'divergences.php');

    $positions = array_map(
        static fn (array $tuple): array => [$tuple['line'], $tuple['column']],
        violationTuples($file)
    );

    expect($positions)->toBe([
        [23, 9],
        [30, 9],
        [36, 6],
        [41, 6],
    ]);
});
