<?php

declare(strict_types=1);

const MANIPULATION_OPERATOR_PLACEMENT = 'CleanCode.Operators.ManipulationOperatorPlacement';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MANIPULATION_OPERATOR_PLACEMENT);
});

it('registers the math and bitwise operators only', function (): void {
    $registered = (new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff())->register();

    sort($registered);

    $expected = [T_PLUS, T_MINUS, T_MULTIPLY, T_DIVIDE, T_MODULUS, T_POW, T_BITWISE_AND, T_BITWISE_OR,
        T_BITWISE_XOR, T_SL, T_SR];

    sort($expected);

    expect($registered)->toBe($expected);
});

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
        ['line' => 56, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 59, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 62, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 65, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 70, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 74, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 78, 'column' => 3, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
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
        ['line' => 146, 'column' => 18, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 151, 'column' => 24, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 156, 'column' => 11, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 158, 'column' => 20, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 168, 'column' => 15, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 175, 'column' => 26, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 184, 'column' => 22, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 190, 'column' => 24, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 202, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 205, 'column' => 14, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 208, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 211, 'column' => 19, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 214, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 217, 'column' => 23, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 220, 'column' => 29, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 229, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 232, 'column' => 21, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 243, 'column' => 17, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 247, 'column' => 23, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 262, 'column' => 13, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 278, 'column' => 25, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 298, 'column' => 34, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
        ['line' => 310, 'column' => 16, 'source' => MANIPULATION_OPERATOR_PLACEMENT . '.OperatorNotLeading'],
    ]);
});

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

it('separates a value-producing brace from a scope-closing one', function (): void {
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php')),
        'line'
    );
    $compliant = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');

    expect($flagged)->toContain(70, 74, 78, 87, 90, 93, 96, 99, 102)
        ->and($compliant->getErrors())->toBe([]);
});

it('reads a bare block as a statement, not as an ownerless value', function (): void {
    $blockOperatorLines = [149, 157, 163, 170];
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php')),
        'line'
    );

    expect($flagged)->not->toContain(...$blockOperatorLines);

    $source = file(fixturePath(sniffFixtureDirectory(MANIPULATION_OPERATOR_PLACEMENT), 'passing.php'));

    foreach ($blockOperatorLines as $line) {
        expect(trim($source[$line - 1]))->toMatch('/^\}\s[+-]$/');
    }
});

it('admits every token that can open a brace dereference', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );

    expect($reflected->getConstant('CURLY_DEREFERENCE_INTRODUCERS'))
        ->toBe([T_DOLLAR, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON]);
});

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

it('classifies every token findStartOfStatement halts on', function (): void {
    $reflected = new ReflectionClass(
        MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff::class
    );

    $classified = array_merge(
        $reflected->getConstant('STATEMENT_ANCHOR_GROUPING_OPENERS'),
        $reflected->getConstant('STATEMENT_ANCHOR_SEPARATORS'),
        $reflected->getConstant('STATEMENT_ANCHOR_BOUNDARY_TOKENS'),
        [T_COLON, T_SEMICOLON]
    );

    $halts = array_merge(
        array_keys(PHP_CodeSniffer\Util\Tokens::$blockOpeners),
        [T_OPEN_SHORT_ARRAY, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO],
        [T_CLOSE_TAG, T_COLON, T_COMMA, T_DOUBLE_ARROW, T_MATCH_ARROW, T_SEMICOLON]
    );

    sort($classified);
    sort($halts);

    expect($classified)->toBe($halts);
});

it('offers a fix for every violation except the one behind a comment', function (): void {
    $fixable = violationFixableFlags(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixable)->toBe(array_merge(array_fill(0, 54, true), [false]));
});

it('fixes a bracketed operator to the statement root indent', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("\$called = someCall(\n    \$value\n    + \$four\n);")
        ->and($fixed)->toContain("\$array = [\n    \$base\n    - \$discount,\n];")
        ->and($fixed)->toContain("    return between(\n        \$left\n        + \$right\n    );");
});

it('anchors a wrapped operator identically either side of every expression divider', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)
        ->toContain("\$named = someCall(\n    name: \$value\n    + \$four,\n);")
        ->and($fixed)->toContain("\$called = someCall(\n    \$value\n    + \$four\n);")
        ->and($fixed)->toContain("\$keyed = [\n    'timeout' => \$base\n    + \$padding,\n];")
        ->and($fixed)->toContain("\$array = [\n    \$base\n    - \$discount,\n];")
        ->and($fixed)->toContain("\$mixed = [\n    \$base\n    + \$one,\n    'key' => \$base\n    + \$two,\n];")
        ->and($fixed)->toContain(
            "for (\n    \$index = 0\n    + \$offset;\n    \$index < \$limit;\n    \$index = \$index\n    + \$step\n)"
        )
        ->and($fixed)->toContain("\$nestedCall = outer(\n    inner(\n        \$base\n    * \$factor,\n    ),\n);")
        ->and($fixed)->toContain(
            "\$nestedArray = [\n    'outer' => [\n        'inner' => \$base\n    * \$factor,\n    ],\n];"
        );
});

it('anchors a wrap inside a brace block on its own statement line', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("    case 1:\n        \$cased = \$base\n            << \$shift;")
        ->and($fixed)->toContain("\$armed = match (\$mode) {\n    default => \$base\n        & \$mask,\n};");
});

it("reads only a for header's semicolons as clause dividers", function (): void {
    $sniff = new MikeBronner\CleanCode\Sniffs\Operators\ManipulationOperatorPlacementSniff();
    $separator = new ReflectionMethod($sniff, 'isForHeaderSeparator');

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

    expect($dividers)->toBe([230, 231, 244, 246, 274, 275, 288, 289])
        ->and(count($terminators))->toBeGreaterThan(30);
});

it('anchors a wrap inside a for clause body on its own statement line', function (): void {
    $fixed = autofixedContents(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($fixed)->toContain("                \$seen = 0;\n        \$scaled = \$base\n            * \$factor;")
        ->and($fixed)->toContain(
            "                    \$seen = 1;\n            \$total = \$this->base\n                + \$seen;"
        );
});

it('leaves a multi-line catch type union alone while still flagging a real bitwise or', function (): void {
    $passing = analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php');
    $failing = violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php'));

    expect($passing->getErrors())->toBe([])
        ->and(array_column($failing, 'line'))->toContain(27);
});

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

it('keeps a for-loop init or increment wrap it cannot defer', function (): void {
    $flagged = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'failing.php')),
        'line'
    );
    $deferred = array_column(
        violationTuples(analyzeFixture(MANIPULATION_OPERATOR_PLACEMENT, 'passing.php')),
        'line'
    );

    expect($flagged)->toContain(229, 232, 243, 247)
        ->and($deferred)->not->toContain(223);
});
