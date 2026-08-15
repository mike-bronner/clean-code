<?php

/**
 * Tests the custom CleanCode.Operators.ManipulationOperatorPlacement sniff
 * (Operators: Manipulative, #59). Fixtures live in
 * tests/fixtures/ManipulationOperatorPlacementSniff/.
 *
 * The sniff is isolated from the rest of the master ruleset so these assertions
 * stay stable as sibling standards land in rules.xml. The one place that
 * *needs* the whole ruleset — that no other sniff reports the same wrap — is
 * tests/Integration/OperatorRulesIntegrationTest.php, which pins every operator
 * source line by line.
 */

declare(strict_types=1);

const MANIPULATION_OPERATOR_PLACEMENT = 'CleanCode.Operators.ManipulationOperatorPlacement';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MANIPULATION_OPERATOR_PLACEMENT);
});

/**
 * The math group (`+ - * / % **`) and the bitwise group (`& | ^ << >>`) — the
 * slice of the standard's operator list no other rule already polices. The
 * assertion is over register() itself rather than over a fixture, so it holds
 * for every token either side claims rather than for whichever ones an example
 * happens to use.
 */
it('registers the math and bitwise operators only', function (): void {
    $registered = (new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff())->register();

    sort($registered);

    $expected = [T_PLUS, T_MINUS, T_MULTIPLY, T_DIVIDE, T_MODULUS, T_POW, T_BITWISE_AND, T_BITWISE_OR,
        T_BITWISE_XOR, T_SL, T_SR];

    sort($expected);

    expect($registered)->toBe($expected);
});

/**
 * The disjointness the split rests on, asserted the same way
 * tests/Standards/BooleanOperatorSpacingTest.php asserts its own: the two
 * sniffs enforcing "an operator must lead the continuation line" share no
 * token, so no wrapped expression is ever reported twice. `.` and the boolean
 * connectives stay with OperatorLineBreak (#35); `~` belongs to neither,
 * having no binary form.
 */
it('shares no token with the sniff that owns the rest of the rule', function (): void {
    $manipulation = (new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff())->register();
    $lineBreak = (new MikeBronner\CleanCode\Sniffs\Operators\OperatorLineBreakSniff())->register();

    expect(array_intersect($manipulation, $lineBreak))->toBe([])
        ->and($lineBreak)->toContain(T_STRING_CONCAT, T_BOOLEAN_AND, T_BOOLEAN_OR)
        ->and($manipulation)->not->toContain(T_BITWISE_NOT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every trailing manipulation operator in failing.php, at its exact line and
 * column. Lines 56-96 are the operand-terminator sweep — a short-array `]`, a
 * postfix `++`/`--`, a backtick, the three value-producing braces, and one
 * dereference per introducing token — each of which the sniff must read as a
 * real left-hand operand rather than exempting the sign as unary. Line 136 is
 * the comment case — reported, but not fixable.
 */
it('flags every trailing operator at its exact line and column', function (): void {
    $tuples = violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 6, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 9, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 10, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 13, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 16, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 19, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 23, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 24, 'column' => 12, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 27, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 28, 'column' => 10, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 29, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 36, 'column' => 19, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 39, 'column' => 26, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 44, 'column' => 9, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 49, 'column' => 9, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // short-array `]`
        ['line' => 56, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // postfix `++` / `--`
        ['line' => 59, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 62, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // backtick shell execution
        ['line' => 65, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // the value-producing braces: match, anonymous class, closure
        ['line' => 70, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 74, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 78, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // the dereference braces, one per introducing token: `->`, `?->`, `$`, `::`
        ['line' => 84, 'column' => 37, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 87, 'column' => 39, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 90, 'column' => 30, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 93, 'column' => 39, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 96, 'column' => 42, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 103, 'column' => 12, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 108, 'column' => 11, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 118, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 128, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 136, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
    ]);
});

/**
 * The operand model's two families that PHP_CodeSniffer itself enumerates are
 * pinned against *its* enumerations rather than against a copy, so a token added
 * upstream fails here instead of silently widening the sniff's blind spot. Each
 * gap this catches is a false negative: an unrecognised operand terminator makes
 * the sniff read a real subtraction as a unary sign and report nothing.
 *
 * Only the members PHP_CodeSniffer groups are derived — a short array's `]` is
 * outside Tokens::$bracketTokens and a heredoc/nowdoc closer is one of six
 * tokens in Tokens::$heredocTokens, so both are named. The braces are the one
 * entry the token alone cannot settle; the fixtures above and below decide those.
 */
it('admits every operand terminator PHP_CodeSniffer enumerates', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );
    $admitted = $reflected->getConstant('OPERAND_END_TOKENS');

    $closers = array_values(array_filter(
        PHP_CodeSniffer\Util\Tokens::$bracketTokens,
        fn ($token): bool => str_contains(PHP_CodeSniffer\Util\Tokens::tokenName($token), '_CLOSE_')
    ));

    expect($admitted)->toContain(...$closers)
        ->and($admitted)->toContain(T_CLOSE_SHORT_ARRAY)
        ->and($admitted)->toContain(...PHP_CodeSniffer\Util\Tokens::$stringTokens)
        ->and($admitted)->toContain(T_END_HEREDOC, T_END_NOWDOC, T_BACKTICK)
        ->and($admitted)->toContain(T_INC, T_DEC);
});

/**
 * The brace half of the operand model, in both directions. A `}` that closes a
 * value (`match`, an anonymous class, a closure, a dereference) continues the
 * expression, so a sign after it is binary; a `}` that closes a statement body
 * does not, so the sign opens a new — discarded — statement and is unary.
 * Flagging the latter would not merely over-report: the fixer would rewrite
 * code this standard has no claim on.
 *
 * Asserted as one test over both fixtures because the token is identical in
 * every case and only what owns the brace separates them — a regression that
 * dropped either half of that reading would satisfy one side alone.
 */
it('separates a value-producing brace from a scope-closing one', function (): void {
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php')),
        'line'
    );
    $compliant = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');

    // match, anonymous class, closure, and the four dereferences — all values.
    expect($flagged)->toContain(70, 74, 78, 84, 87, 90, 93, 96)
        // if, while, foreach, for, switch, try/finally, function — all statements.
        ->and($compliant->getErrors())->toBe([]);
});

/**
 * The half of that reading PHP_CodeSniffer's `scope_condition` cannot settle.
 * It leaves the field unset on two unrelated constructs — a dereference, whose
 * `}` closes a value, and a bare compound-statement block (`{ … }` with no
 * owning keyword), whose `}` closes a statement exactly as an `if` body's does.
 * Reading an absent owner as "value" flags `{ … } - 5;` — two independent
 * statements — and the fixer then welds them into one, which is why this is
 * pinned separately from the owned-brace cases above.
 *
 * Asserted over the exact bare-block lines rather than over the fixture's
 * emptiness, so the four blocks stay named as the regression they guard: the
 * check that a brace with no owner is a value only when a dereference token
 * opened it.
 */
it('reads a bare block as a statement, not as an ownerless value', function (): void {
    $blockOperatorLines = [138, 146, 152, 159];
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php')),
        'line'
    );

    expect($flagged)->not->toContain(...$blockOperatorLines);

    // Each line is genuinely a `}`-then-sign wrap, so the assertion above has
    // something to discriminate — a fixture edit that moved these cases away
    // would otherwise silently make it vacuous.
    $source = file(fixturePath(sniffFixtureDirectory(MANIPULATION_OPERATOR_PLACEMENT), 'passing.php'));

    foreach ($blockOperatorLines as $line) {
        expect(trim($source[$line - 1]))->toMatch('/^\}\s[+-]$/');
    }
});

/**
 * The introducers are a hand-maintained set, so this pins what closes it: every
 * brace-dereference syntax PHP has. A missing member costs a false negative —
 * the sniff reads a real subtraction after the fetch as a unary sign — which is
 * the failure mode this direction is chosen for, but it is still a gap.
 */
it('admits every token that can open a brace dereference', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );

    expect($reflected->getConstant('CURLY_DEREFERENCE_INTRODUCERS'))
        // `${$name}` and `Thing::${$name}`; `$object->{$name}`;
        // `$object?->{$name}`; `Thing::{$name}()` and `Thing::{$name}`.
        ->toBe([T_DOLLAR, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]);
});

/**
 * Moving the operator across a comment sitting between the two operands would
 * reorder the comment, so that one violation is reported without a fixer while
 * every other one carries one. Asserted per violation rather than as a count,
 * so a fixer that silently stopped offering itself elsewhere would fail here.
 */
it('offers a fix for every violation except the one behind a comment', function (): void {
    $fixable = violationFixableFlags(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    // The flags come back in report order, so the comment case is the last of
    // the thirty-two violations the test above pins line by line.
    expect($fixable)->toBe(array_merge(array_fill(0, 31, true), [false]));
});

/**
 * The fixer's own output, byte for byte, is asserted by the contract sweep. The
 * point of interest here is *where* the operator lands: one level past the
 * statement's root line, level with its operand — including when the operator
 * sits inside an `if (...)` condition, a call-argument list, or an array
 * literal, which is where an anchor resolved with findStartOfStatement() alone
 * would indent one level too deep.
 */
it('fixes a bracketed operator to the statement root indent', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("\$called = someCall(\n    \$value\n    + \$four\n);")
        ->and($fixed)->toContain("\$array = [\n    \$base\n    - \$discount,\n];")
        ->and($fixed)->toContain("    return between(\n        \$left\n        + \$right\n    );");
});

/**
 * A `|` separating exception types in a multi-line `catch` clause stays
 * T_BITWISE_OR — PHP_CodeSniffer only retokenises a union to T_TYPE_UNION in
 * parameter, return, and property positions — so without the catch-clause
 * exemption the sniff would flag valid code and the fixer would reformat it.
 * Pinned by mutating the fixture rather than the sniff: the same shape outside
 * a catch clause is still reported.
 */
it('leaves a multi-line catch type union alone while still flagging a real bitwise or', function (): void {
    $passing = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');
    $failing = violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($passing->getErrors())->toBe([])
        ->and(array_column($failing, 'line'))->toContain(27);
});
