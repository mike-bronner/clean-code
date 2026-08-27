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
 * column. Lines 56-102 and 202-220 are the operand-terminator sweep — a
 * short-array `]`, a postfix `++`/`--`, a backtick, the three value-producing
 * braces, one dereference per introducing token, and the value family — each of
 * which the sniff must read as a real left-hand operand rather than exempting
 * the sign as unary. Lines 229-247 are the for-loop clauses this sniff owns
 * because the sniff it otherwise defers to never reads them, and lines 278 and
 * 298 are the wraps inside a closure and an anonymous class body written
 * directly in a `for` clause. Line 310 is the comment case — reported, but not
 * fixable.
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
        // the dereference braces, one per introducing token: `->`, `?->`, `$`,
        // `::` — then the dynamic static call, whose operand ends on the call's
        // `)` rather than on the brace
        ['line' => 87, 'column' => 37, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 90, 'column' => 39, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 93, 'column' => 30, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 96, 'column' => 40, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 99, 'column' => 39, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 102, 'column' => 42, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 109, 'column' => 12, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 114, 'column' => 11, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 124, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 134, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // the anchor sweep: a named argument's `:`, an array key's `=>`, and the
        // keyed/unkeyed pair inside one literal
        ['line' => 146, 'column' => 18, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 151, 'column' => 24, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 156, 'column' => 11, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 158, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // a nested call and the array literal it must agree with
        ['line' => 168, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 175, 'column' => 26, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // the boundary side: a `match` arm and a `switch` case body
        ['line' => 184, 'column' => 22, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 190, 'column' => 24, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // the value family: a constant, a float, `true`, `false`, `null`, a
        // single-quoted string, and an array index's `]` — each behind a
        // `+`/`-`, the only operators the operand model gates
        ['line' => 202, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 205, 'column' => 14, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 208, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 211, 'column' => 19, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 214, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 217, 'column' => 23, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 220, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // a for-loop's init and increment clauses, on both classifications of
        // its condition
        ['line' => 229, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 232, 'column' => 21, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 243, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 247, 'column' => 23, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // a closure argument's semicolons, which enclose nothing a `for` owns
        ['line' => 262, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        // a closure body and an anonymous class body written directly in a
        // `for` clause: their semicolons sit inside the `for`'s own parentheses
        // but divide nothing
        ['line' => 278, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 298, 'column' => 34, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 310, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
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
 * Membership is not behaviour: the test above pins what the set *holds*, and a
 * member correct today but exercised by no fixture can be dropped without a
 * single assertion noticing. So this closes the set from the other end — every
 * admitted token must actually terminate a left-hand operand somewhere in
 * failing.php, derived from the violations the sniff reports rather than from a
 * second hand-written list that could drift from the first.
 *
 * Only the violations on a `+`, `-` or `&` count. Those are the operators whose
 * reading the operand model gates; behind any other operator the terminator is
 * never consulted, so a case there would satisfy this test while pinning
 * nothing. Adding a member without a fixture fails here, which is the gap this
 * exists to close.
 */
it('exercises every operand terminator it admits', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );
    $file = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php');
    $errors = $file->getErrors();
    $tokens = $file->getTokens();
    $exercised = [];

    foreach ($tokens as $pointer => $token) {
        if (
            isset($errors[$token['line']][$token['column']]) === false
            || in_array($token['code'], $reflected->getConstant('UNARY_CAPABLE'), true) === false
        ) {
            continue;
        }

        $previous = $file->findPrevious(PHP_CodeSniffer\Util\Tokens::$emptyTokens, ($pointer - 1), null, true);
        $exercised[] = $tokens[$previous]['code'];
    }

    expect($exercised)->toContain(...$reflected->getConstant('OPERAND_END_TOKENS'));
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

    // match, anonymous class, closure, and the dereferences — all values.
    expect($flagged)->toContain(70, 74, 78, 87, 90, 93, 96, 99, 102)
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
    $blockOperatorLines = [149, 157, 163, 170];
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
        // `$object?->{$name}`; `Thing::{$name}`.
        ->toBe([T_DOLLAR, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]);
});

/**
 * The same set closed from the behaviour end, exactly as the operand model's is
 * above: membership alone is satisfied by a member no fixture ever routes a
 * violation through, and such a member can be dropped without an assertion
 * noticing. So every introducer must actually open a brace that terminates a
 * left-hand operand in failing.php, derived from the violations the sniff
 * reports rather than from a second hand-written list.
 *
 * Only a `}` carrying no `scope_condition` counts — the owned braces are
 * settled by VALUE_PRODUCING_SCOPE_OWNERS and never reach the introducer — and
 * only a violation on a UNARY_CAPABLE operator, whose reading is what the
 * introducer decides. `Thing::{$name}()` satisfies neither: its operand ends on
 * the call's `)`, which is why the bare `Thing::{$name}` fetch beside it is the
 * `::` case and not that one.
 */
it('exercises every brace-dereference introducer it admits', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );
    $file = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php');
    $errors = $file->getErrors();
    $tokens = $file->getTokens();
    $exercised = [];

    foreach ($tokens as $pointer => $token) {
        if (
            isset($errors[$token['line']][$token['column']]) === false
            || in_array($token['code'], $reflected->getConstant('UNARY_CAPABLE'), true) === false
        ) {
            continue;
        }

        $closer = $file->findPrevious(PHP_CodeSniffer\Util\Tokens::$emptyTokens, ($pointer - 1), null, true);

        if (
            $tokens[$closer]['code'] !== T_CLOSE_CURLY_BRACKET
            || isset($tokens[$closer]['scope_condition']) === true
        ) {
            continue;
        }

        $introducer = $file->findPrevious(
            PHP_CodeSniffer\Util\Tokens::$emptyTokens,
            ($tokens[$closer]['bracket_opener'] - 1),
            null,
            true
        );
        $exercised[] = $tokens[$introducer]['code'];
    }

    expect($exercised)->toContain(...$reflected->getConstant('CURLY_DEREFERENCE_INTRODUCERS'));
});

/**
 * The continuation anchor's third hand-maintained set, swept whole rather than
 * case by case. Every token PHP_CodeSniffer halts its findStartOfStatement()
 * walk on either divides an expression (the anchor escapes past it), ends a
 * statement (it does not), or is `:` — the one token whose answer depends on
 * which colon it is. A token in none of the three is a token the walk meets and
 * silently mis-anchors, one indent level too deep.
 *
 * The failure mode is why this is asserted over the sets and not over fixtures:
 * an unclassified token costs a wrong indent on a construct no fixture happens
 * to hold, and PHP_CodeSniffer can add one without this package changing at all.
 */
it('classifies every token findStartOfStatement halts on', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );

    $classified = array_merge(
        $reflected->getConstant('STATEMENT_ANCHOR_GROUPING_OPENERS'),
        $reflected->getConstant('STATEMENT_ANCHOR_SEPARATORS'),
        $reflected->getConstant('STATEMENT_ANCHOR_BOUNDARY_TOKENS'),
        // Decided per occurrence, by what encloses them — a named argument's
        // colon and a `for` header's semicolons divide an expression; every
        // other colon and semicolon ends a statement.
        [T_COLON, T_SEMICOLON]
    );

    // PHP_CodeSniffer\Files\File::findStartOfStatement() halts on its
    // $startTokens — Tokens::$blockOpeners plus a short array's `[` and the two
    // opening tags — and on its $endTokens. blockOpeners is read from
    // PHP_CodeSniffer live; the rest are local variables there, so they are
    // named here and this assertion is what keeps the copy honest.
    $halts = array_merge(
        array_keys(PHP_CodeSniffer\Util\Tokens::$blockOpeners),
        [T_OPEN_SHORT_ARRAY, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO],
        [T_CLOSE_TAG, T_COLON, T_COMMA, T_DOUBLE_ARROW, T_MATCH_ARROW, T_SEMICOLON]
    );

    sort($classified);
    sort($halts);

    expect($classified)->toBe($halts);
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
    // the fifty-five violations the test above pins line by line.
    expect($fixable)->toBe(array_merge(array_fill(0, 54, true), [false]));
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
 * The invariant the anchor exists to hold, asserted as the pairs that have to
 * agree rather than as bytes: two structurally identical wraps land on the same
 * indent whatever expression-dividing token stands between the operand and the
 * statement root. Each pair discriminates — drop `=>` from the escape set and
 * the keyed member moves to eight spaces while its keyless twin stays at four,
 * which is the stair-stepping continuationIndent() exists to prevent.
 *
 * Pinned per pair rather than by the contract sweep's byte comparison alone, so
 * a regression names the divider it broke instead of reporting a whole-file diff.
 */
it('anchors a wrapped operator identically either side of every expression divider', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)
        // a named argument's `:`, against the positional argument it must match
        ->toContain("\$named = someCall(\n    name: \$value\n    + \$four,\n);")
        ->and($fixed)->toContain("\$called = someCall(\n    \$value\n    + \$four\n);")
        // an array key's `=>`, against the keyless element it must match
        ->and($fixed)->toContain("\$keyed = [\n    'timeout' => \$base\n    + \$padding,\n];")
        ->and($fixed)->toContain("\$array = [\n    \$base\n    - \$discount,\n];")
        // both dividers inside one literal — the sharpest form of the same pair
        ->and($fixed)->toContain("\$mixed = [\n    \$base\n    + \$one,\n    'key' => \$base\n    + \$two,\n];")
        // a for header's `;`, as the init clause against the increment clause
        // it must match — read as a statement terminator, the increment anchors
        // on its own indented line while the init escapes to the `for`
        ->and($fixed)->toContain(
            "for (\n    \$index = 0\n    + \$offset;\n    \$index < \$limit;\n    \$index = \$index\n    + \$step\n)"
        )
        // a nested call, against the nested array literal it must match
        ->and($fixed)->toContain("\$nestedCall = outer(\n    inner(\n        \$base\n    * \$factor,\n    ),\n);")
        ->and($fixed)->toContain(
            "\$nestedArray = [\n    'outer' => [\n        'inner' => \$base\n    * \$factor,\n    ],\n];"
        );
});

/**
 * The other half of that walk: a brace block genuinely ends the statement, so a
 * wrap inside one anchors on its own line and not on whatever encloses the
 * block. Asserted next to the escapes above because a walk that escaped
 * *everything* would satisfy those and still be wrong here — which is exactly
 * what the `switch` case body catches. Escaping past `:` unconditionally, the
 * obvious way to admit a named argument's colon, pulls that body's indent back
 * to the `case` label's line and fails this test.
 *
 * The `match` arm is coverage, not a discriminator, and is pinned as such: an
 * arm's condition and its body share a line, so the anchor lands on that same
 * line whether the walk escapes past `T_MATCH_ARROW` or stops at it. No reading
 * of that token changes this output, which is why the sniff classifies it by
 * what the construct *is* rather than by a behaviour a test could pin. The value
 * here is the regression guard for a future rewrite that does read it
 * differently.
 */
it('anchors a wrap inside a brace block on its own statement line', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("    case 1:\n        \$cased = \$base\n            << \$shift;")
        ->and($fixed)->toContain("\$armed = match (\$mode) {\n    default => \$base\n        & \$mask,\n};");
});

/**
 * The `;` half of the anchor's per-occurrence classification, asserted on the
 * method rather than through the fixer's output — because the narrowing has no
 * output to assert. Escaping past an ordinary `;` walks to the previous
 * statement's start, and consecutive statements in a block share an indent, so
 * the continuation lands in the same column read either way. Verified, not
 * assumed: reading *every* `;` as a divider leaves the whole suite green.
 *
 * That makes the reading unfalsifiable through the fixed source and is exactly
 * why it is pinned here instead. An anchor that walks out of the statement it
 * belongs to is wrong on the classification even where the coincidence of equal
 * indents hides it, and a future construct that breaks the coincidence would
 * turn a silent wrong reading into a wrong indent with no test in between.
 *
 * Asserted over every semicolon in the fixture at once, so the narrow reading
 * and the fail-open one cannot both satisfy it.
 */
it("reads only a for header's semicolons as clause dividers", function (): void {
    $sniff = new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff();
    $separator = new ReflectionMethod($sniff, 'isForHeaderSeparator');
    $separator->setAccessible(true);

    $file = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php');
    $dividers = [];
    $terminators = [];

    foreach ($file->getTokens() as $pointer => $token) {
        if ($token['code'] !== T_SEMICOLON) {
            continue;
        }

        if ($separator->invoke($sniff, $file, $pointer) === true) {
            $dividers[] = $token['line'];

            continue;
        }

        $terminators[] = $token['line'];
    }

    // The four for headers' eight clause dividers, and no other semicolon in a
    // fixture that is mostly semicolon-terminated statements — including the
    // ones inside a closure body and an anonymous class body written directly
    // in a clause, which report the same `for`-owned parenthesis the dividers
    // do and are told from them only by the top-level walk.
    expect($dividers)->toBe([230, 231, 244, 246, 274, 275, 288, 289])
        ->and(count($terminators))->toBeGreaterThan(30);
});

/**
 * The behavioural half of the same reading, where it does have output to
 * assert. A closure or an anonymous class written directly in a `for` clause
 * puts its own statements inside the parentheses the `for` owns, so each of
 * their semicolons reports that same parenthesis — indistinguishable from the
 * header's own two by the owner alone. Read as dividers, the anchor escapes out
 * of the wrapped statement and into the sibling statement above it, and the
 * fixer indents the continuation to that sibling instead.
 *
 * Both fixtures put the sibling statement at a deliberately different indent
 * from the wrap, so the wrong donor line shows in the output rather than hiding
 * behind two lines that happen to share a column — the coincidence that keeps
 * the ordinary `;` case unfalsifiable through the fixed source.
 */
it('anchors a wrap inside a for clause body on its own statement line', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    // The closure body's sibling sits at sixteen spaces; the wrap's own
    // statement at eight, so its continuation belongs at twelve.
    expect($fixed)->toContain("                \$seen = 0;\n        \$scaled = \$base\n            * \$factor;")
        // The anonymous class method's sibling sits at twenty; the wrap's own
        // statement at twelve, so its continuation belongs at sixteen.
        ->and($fixed)->toContain(
            "                    \$seen = 1;\n            \$total = \$this->base\n                + \$seen;"
        );
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

/**
 * The exemption exempts `&` on the same footing as `|`, and that is a second
 * live branch rather than a restatement of the first: PHP_CodeSniffer leaves a
 * catch clause's separator a plain T_BITWISE_AND/T_BITWISE_OR, so each token
 * reaches the check on its own. PHP itself rejects an intersection type in a
 * catch, but the sniff never compiles the file — the tokenizer is the only
 * reader — which is what makes the fixture legitimate.
 *
 * Asserted over the fixture's own lines rather than over its emptiness, so a
 * fixture edit that moved either clause away cannot make this vacuous, and a
 * regression naming only `|` fails on the `&` line specifically.
 */
it('exempts both catch-clause type separators, not only the union', function (): void {
    $separatorLines = [64, 75];
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php')),
        'line'
    );

    expect($flagged)->not->toContain(...$separatorLines);

    $source = file(fixturePath(sniffFixtureDirectory(MANIPULATION_OPERATOR_PLACEMENT), 'passing.php'));

    foreach ($separatorLines as $line) {
        expect(trim($source[$line - 1]))->toMatch('/^\} catch \(\w+ [|&]$/');
    }
});

/**
 * A for-loop's init and increment clauses sit inside the condition's
 * parentheses but outside the span CleanCode.Conditionals.OneConditionPerLine
 * checks — it confines itself to the clause between the two semicolons — so
 * standing down there would drop the violation entirely rather than hand it
 * over. Both clauses are pinned on both classifications of the condition,
 * because the deferral reads a boolean-carrying condition down a different
 * branch from a single one and only the region check rules out both.
 *
 * The passing side is the same boundary inverted: a boolean in the *init*
 * clause must not reach the classification of the condition, or a wrap the
 * other sniff owns gets reported twice.
 */
it('keeps a for-loop init or increment wrap it cannot defer', function (): void {
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php')),
        'line'
    );
    $deferred = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php')),
        'line'
    );

    // failing.php — init and increment, with a single condition then a multi one.
    expect($flagged)->toContain(229, 232, 243, 247)
        // passing.php:223 — the condition clause's own wrap, which
        // OneConditionPerLine owns and still owns with a boolean sitting in
        // the init clause beside it.
        ->and($deferred)->not->toContain(223);
});
