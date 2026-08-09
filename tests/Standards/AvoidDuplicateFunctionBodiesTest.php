<?php

/**
 * Tests the custom CleanCode.Functions.AvoidDuplicateFunctionBodies sniff
 * (partial enforcement of Pattern: Don't Repeat Yourself (DRY), #134 out of
 * #4). Fixtures live in tests/fixtures/AvoidDuplicateFunctionBodiesSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors: the
 * standard tolerates duplication until an abstraction is warranted, so a
 * report is an abstraction *candidate*, not a defect. There is therefore no
 * autofixed fixture, and the tests below prove no report is fixable.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 *
 * Every threshold below is configured as a *string*, the way PHP_CodeSniffer
 * hands a ruleset's <property> value to a sniff — it passes on the text it read
 * out of the XML and never casts it. That is why the sniff's property is
 * untyped and cast where it is read, and why these tests set it the same way
 * rather than with an int a real ruleset could never deliver.
 */

declare(strict_types=1);

const AVOID_DUPLICATE_FUNCTION_BODIES = 'CleanCode.Functions.AvoidDuplicateFunctionBodies';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(AVOID_DUPLICATE_FUNCTION_BODIES);
});

/**
 * passing.php is deliberately full of declarations the sniff walks. What
 * silences it is the comparison: four near-miss pairs that each differ by one
 * token, identical bodies below the threshold, identical closure bodies the
 * sniff does not register on, and two bodyless declarations. Dropping token
 * *content* from the normalized stream (comparing types alone) reddens this
 * test on the renamed-variable and changed-literal pairs.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One warning per duplicate, reported at the `function` keyword of the copy
 * and naming the line of the original.
 *
 * The Billing group holds three identical bodies and yields two warnings, both
 * against chargeCard() on line 28 — the first occurrence is the original, not
 * a duplicate of itself. The remaining two warnings cover the other shapes:
 * identical methods in different classes of the one file, and identical plain
 * functions at file scope.
 */
it('warns once per duplicate body, citing the original line', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 40, 'column' => 15, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 49, 'column' => 13, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 71, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 88, 'column' => 1, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * The message has to carry the original's name and line, because the warning
 * sits on the copy and a reader needs somewhere to compare it against. A group
 * of three points every copy at the first occurrence rather than chaining each
 * copy to the one before it.
 */
it('names the original declaration and its line in the message', function (): void {
    $messages = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'failing.php')->getWarnings();

    expect($messages[40][15][0]['message'])
        ->toContain('The body of chargeAccount() is identical to chargeCard() on line 28')
        ->and($messages[49][13][0]['message'])
        ->toContain('The body of chargeWallet() is identical to chargeCard() on line 28');
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

/**
 * Detection only. Extracting shared logic and rewriting both call sites is a
 * design change, so a fixable count above zero would mean phpcbf had rewritten
 * something the sniff has no safe rewrite for. getFixableCount() is used
 * rather than violationFixableFlags(), which reads getErrors() only and so
 * would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * boundaries.php holds five identical pairs at 0, 1, 2, 2, and 3 statements.
 * At the default threshold of three, only the three-statement pair on line 86
 * is reported.
 *
 * The pair on line 69 is the guard on the statement count itself: its body
 * carries four semicolons, two of which punctuate a `for` header. Counting
 * those would put it at four and report it here, so deleting the header
 * exclusion reddens this test.
 */
it('compares only bodies at or above the default statement threshold', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'boundaries.php');

    expect(warningTuples($file))->toBe([
        ['line' => 86, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * Lowering the threshold to two brings in both two-statement pairs — the plain
 * one on line 53 and the `for` one on line 69, whose two real statements now
 * clear the cut. That the two arrive together is the second half of the
 * header-exclusion proof: the `for` pair behaves as a two-statement body, not
 * a four-statement one.
 */
it('honours a lowered statement threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_FUNCTION_BODIES,
        'boundaries.php',
        static function (object $sniff): void {
            $sniff->minimumStatements = '2';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 53, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 69, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 86, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * The threshold is floored at one, so a configured zero behaves as one: the
 * single-statement pair on line 41 arrives, the empty-bodied pair on line 32
 * does not. Two identical empty stubs are not a duplication anyone can act on,
 * and a floor of one keeps that true whatever a consumer configures.
 */
it('floors the statement threshold at one', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_FUNCTION_BODIES,
        'boundaries.php',
        static function (object $sniff): void {
            $sniff->minimumStatements = '0';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 41, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 53, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 69, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 86, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * A non-numeric threshold casts to zero and is then floored to one, so a
 * mistyped ruleset property reports more than intended rather than silently
 * reporting nothing. PHP_CodeSniffer hands a <property> value over as the raw
 * string it read, which is why the property is untyped: a native int
 * declaration would turn this into an uncatchable TypeError instead.
 */
it('reports more, not less, on a mistyped threshold', function (): void {
    $file = analyzeFixture(
        AVOID_DUPLICATE_FUNCTION_BODIES,
        'boundaries.php',
        static function (object $sniff): void {
            $sniff->minimumStatements = 'not-a-number';
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 41, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 53, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 69, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
        ['line' => 86, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * The sniff registers on T_OPEN_TAG and scans the whole file from the first
 * one, so every later open tag must be ignored. multiple-open-tags.php carries
 * three, separated by inline HTML, around a single duplicated pair: dropping
 * the guard runs the scan once per open tag and reports line 29 three times.
 */
it('scans the file once however many open tags it carries', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'multiple-open-tags.php');

    expect(warningTuples($file))->toBe([
        ['line' => 29, 'column' => 1, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});

/**
 * Only outermost declarations are compared, which pins two things at once.
 *
 * Registry's two methods are identical down to the anonymous class each
 * returns, and produce exactly one warning — the inner build() pair is not
 * reported a second time. Ledger's two methods differ, so the identical run()
 * bodies inside their anonymous classes go unreported: the deliberate blind
 * spot the rule buys, in exchange for never restating one duplication at two
 * nesting levels and for keeping the scan linear in the file's token count.
 *
 * Dropping the nesting guard reddens this test twice over — the inner run()
 * pair appears on line 43 and the inner build() pair on line 72.
 */
it('compares outermost declarations only', function (): void {
    $file = analyzeFixture(AVOID_DUPLICATE_FUNCTION_BODIES, 'nested-declarations.php');

    expect(warningTuples($file))->toBe([
        ['line' => 69, 'column' => 12, 'source' => AVOID_DUPLICATE_FUNCTION_BODIES . '.Found'],
    ]);
});
