<?php

/**
 * Integration test for the Generic.PHP.DiscourageGoto rule as configured in the
 * master rules.xml, which replaces PHPMD's Design/GotoStatement rule (issue
 * #109). Fixtures live in tests/fixtures/DiscourageGotoSniff/.
 *
 * There is no autofixed.php because the rule is not auto-fixable —
 * Generic\Sniffs\PHP\DiscourageGotoSniff reports through addWarning() and
 * registers no fixer, so phpcbf cannot act on it, and PHPMD offers no auto-fix
 * for a goto either. The fixable-count test below pins that, so the absent
 * autofix fixture stays an asserted fact rather than an assumption.
 *
 * Two rules.xml overrides are pinned here:
 *
 *   - Severity. The sniff reports a *warning* out of the box; rules.xml raises
 *     it to an error, so a goto fails a phpcs run the way it fails a phpmd run.
 *     That is why the tests assert the reports land in getErrors() and that
 *     getWarnings() stays empty.
 *   - Message. The stock text calls goto "discouraged", which contradicts that
 *     error severity and names no remedy. rules.xml overrides it, which PHPCS
 *     only honours when the rule is referenced by its message code
 *     (Generic.PHP.DiscourageGoto.Found) rather than by sniff name — pinning
 *     the text here is what stops that reference being "simplified" back to the
 *     sniff name, which silently drops the override while every other
 *     assertion in this file keeps passing.
 */

declare(strict_types=1);

const GOTO_SNIFF = 'Generic.PHP.DiscourageGoto';

const GOTO_MESSAGE = 'Use of the goto construct is disallowed; replace it with standard '
    . 'control structures and separate methods.';

/**
 * Every violation failing.php raises, as line => column. Verified against a
 * live phpcs run over the fixture, not hand-counted.
 *
 *   11  top:        label, file scope
 *   15  goto top;   file scope
 *   24  goto x;     inside a function (PHPMD's own documented example)
 *   27  x:          label inside a function
 *   37  goto done;  inside a while inside a foreach inside a method
 *   41  done:       label inside a method
 *   48  goto end;   first of two jumps to one label
 *   52  goto end;   second of two jumps to one label
 *   55  end:        the shared label
 */
const GOTO_VIOLATION_POSITIONS = [
    11 => 1,
    15 => 5,
    24 => 9,
    27 => 5,
    37 => 17,
    41 => 9,
    48 => 13,
    52 => 13,
    55 => 9,
];

/**
 * The subset of the above that PHPMD 2.15.0 itself reports on the same fixture,
 * confirmed by running `phpmd failing.php text design` against a live install.
 * PHPMD's rule is MethodAware/FunctionAware, so it reaches only goto statements
 * written inside a function or a method.
 */
const GOTO_LINES_PHPMD_ALSO_REPORTS = [24, 37, 48, 52];

/**
 * The goto statements in failing.php, as opposed to the target labels. Split
 * out so the label-divergence test below cannot pass by accident if the label
 * lines were quietly folded into the statement list.
 */
const GOTO_STATEMENT_LINES = [15, 24, 37, 48, 52];

const GOTO_LABEL_LINES = [11, 27, 41, 55];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(GOTO_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every goto statement and target label at its own line and column', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    expect(array_keys($errors))->toBe(array_keys(GOTO_VIOLATION_POSITIONS));

    foreach (GOTO_VIOLATION_POSITIONS as $line => $column) {
        expect(array_keys($errors[$line]))->toBe([$column]);

        $lineErrors = $errors[$line][$column];

        expect($lineErrors)->toHaveCount(1)
            ->and($lineErrors[0]['source'])->toBe(GOTO_SNIFF . '.Found');
    }
});

/**
 * The statement and label lists together must account for every reported line,
 * and must not overlap. Without this, either list could drift out of step with
 * GOTO_VIOLATION_POSITIONS and the divergence tests below would still pass.
 */
it('splits its reported lines into goto statements and labels with nothing left over', function (): void {
    $reported = array_keys(GOTO_VIOLATION_POSITIONS);
    $split = array_merge(GOTO_STATEMENT_LINES, GOTO_LABEL_LINES);

    sort($split);

    expect($split)->toBe($reported)
        ->and(array_intersect(GOTO_STATEMENT_LINES, GOTO_LABEL_LINES))->toBe([]);
});

it('reports goto usage as errors rather than warnings', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(GOTO_VIOLATION_POSITIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The rules.xml <message> override, pinned against the stock sniff text. The
 * override is only honoured because rules.xml references the rule by its
 * message code; drop back to the sniff name and every violation reverts to
 * "Use of the GOTO language construct is discouraged" — which this asserts
 * against explicitly, so the failure names the cause.
 */
it('replaces the stock discouraged wording with the disallowed wording', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    $messages = [];

    foreach ($errors as $columns) {
        foreach ($columns as $violations) {
            foreach ($violations as $violation) {
                $messages[] = $violation['message'];
            }
        }
    }

    expect($messages)->toHaveCount(count(GOTO_VIOLATION_POSITIONS))
        ->and(array_unique($messages))->toBe([GOTO_MESSAGE])
        ->and($messages)->not->toContain('Use of the GOTO language construct is discouraged');
});

it('reports goto usage without offering an auto-fix', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'failing.php');

    // Guard against a vacuous pass: an empty report also has zero fixable
    // violations, so pin that the violations are actually there first.
    expect($file->getErrorCount())->toBe(count(GOTO_VIOLATION_POSITIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))
        ->toBe(array_fill(0, count(GOTO_VIOLATION_POSITIONS), false));
});

/**
 * Every line PHPMD reports is also reported here. This is the mapping's whole
 * point — if it ever stops holding, phpmd has to run separately again for this
 * rule and the docs/phpmd claim is false.
 */
it('covers every line PHPMD itself reports', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    foreach (GOTO_LINES_PHPMD_ALSO_REPORTS as $line) {
        expect($errors)->toHaveKey($line);
    }
});

/**
 * First deliberate divergence: PHPMD flags only the goto statement, never the
 * target label, because its rule walks for GotoStatement nodes alone. The sniff
 * flags both. A label is reachable only by goto, so reporting it is a true
 * positive, and removing the jump without removing the label leaves dead
 * syntax behind. Asserted as a strict superset rather than by count so the
 * intent survives a fixture gaining another case.
 */
it('flags target labels, which PHPMD does not', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    foreach (GOTO_LABEL_LINES as $line) {
        expect($errors)->toHaveKey($line)
            ->and(GOTO_LINES_PHPMD_ALSO_REPORTS)->not->toContain($line);
    }
});

/**
 * Second deliberate divergence: PHPMD's rule implements MethodAware and
 * FunctionAware only, so a goto written at file scope is invisible to it.
 * Line 15 of the fixture is exactly that, and confirmed unreported by a live
 * PHPMD 2.15.0 run.
 */
it('flags a goto at file scope, which PHPMD does not', function (): void {
    $errors = analyzeFixture(GOTO_SNIFF, 'failing.php')->getErrors();

    expect($errors)->toHaveKey(15)
        ->and(GOTO_LINES_PHPMD_ALSO_REPORTS)->not->toContain(15);
});

/**
 * PHPCS has no T_GOTO_LABEL of its own: the tokenizer rewrites any T_STRING
 * followed by a single colon into one unless the nearest preceding `case`, `?`,
 * or `enum` says otherwise (Tokenizers/PHP.php). Switch cases, ternary arms,
 * PHP 8 named arguments, alternative-syntax blocks, enum backing types, and
 * `::` constant access are all `identifier :` sequences that must not trip it —
 * as are methods literally named `goto`, legal since PHP 7.0. The boundary
 * fixture pins every one of them.
 */
it('does not flag goto lookalikes', function (): void {
    $file = analyzeFixture(GOTO_SNIFF, 'boundaries.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});
