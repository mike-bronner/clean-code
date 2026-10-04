<?php

declare(strict_types=1);

const MULTI_LINE_STATEMENT_INDENT = 'CleanCode.WhiteSpace.MultiLineStatementIndent';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTI_LINE_STATEMENT_INDENT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each misindented line at its own line and column', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'failing.php');

    $incorrect = MULTI_LINE_STATEMENT_INDENT . '.IncorrectIndent';
    $closeBracket = MULTI_LINE_STATEMENT_INDENT . '.CloseBracketIndent';

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 1, 'source' => $incorrect],
        ['line' => 8, 'column' => 3, 'source' => $incorrect],
        ['line' => 12, 'column' => 9, 'source' => $incorrect],
        ['line' => 14, 'column' => 9, 'source' => $closeBracket],
        ['line' => 19, 'column' => 5, 'source' => $incorrect],
        ['line' => 25, 'column' => 9, 'source' => $incorrect],
        ['line' => 32, 'column' => 9, 'source' => $incorrect],
        ['line' => 39, 'column' => 5, 'source' => $incorrect],
        ['line' => 45, 'column' => 1, 'source' => $incorrect],
        ['line' => 51, 'column' => 9, 'source' => $incorrect],
        ['line' => 58, 'column' => 9, 'source' => $incorrect],
        ['line' => 63, 'column' => 9, 'source' => $incorrect],
        ['line' => 64, 'column' => 9, 'source' => $incorrect],
        ['line' => 70, 'column' => 5, 'source' => $incorrect],
        ['line' => 76, 'column' => 9, 'source' => $incorrect],
        ['line' => 84, 'column' => 3, 'source' => $closeBracket],
        ['line' => 90, 'column' => 9, 'source' => $incorrect],
        ['line' => 92, 'column' => 1, 'source' => $closeBracket],
        ['line' => 98, 'column' => 1, 'source' => $incorrect],
        ['line' => 105, 'column' => 1, 'source' => $incorrect],
        ['line' => 113, 'column' => 1, 'source' => $incorrect],
        ['line' => 121, 'column' => 3, 'source' => $closeBracket],
        ['line' => 131, 'column' => 1, 'source' => $incorrect],
        ['line' => 136, 'column' => 1, 'source' => $incorrect],
        ['line' => 146, 'column' => 9, 'source' => $incorrect],
        ['line' => 153, 'column' => 9, 'source' => $incorrect],
        ['line' => 161, 'column' => 1, 'source' => $incorrect],
        ['line' => 171, 'column' => 1, 'source' => $incorrect],
        ['line' => 177, 'column' => 1, 'source' => $incorrect],
        ['line' => 186, 'column' => 9, 'source' => $incorrect],
        ['line' => 191, 'column' => 9, 'source' => $incorrect],
        ['line' => 198, 'column' => 9, 'source' => $incorrect],
        ['line' => 206, 'column' => 9, 'source' => $incorrect],
        ['line' => 207, 'column' => 9, 'source' => $incorrect],
        ['line' => 208, 'column' => 9, 'source' => $incorrect],
        ['line' => 217, 'column' => 9, 'source' => $incorrect],
        ['line' => 224, 'column' => 1, 'source' => $incorrect],
        ['line' => 231, 'column' => 1, 'source' => $incorrect],
        ['line' => 239, 'column' => 5, 'source' => $incorrect],
        ['line' => 248, 'column' => 3, 'source' => $incorrect],
        ['line' => 269, 'column' => 1, 'source' => $incorrect],
        ['line' => 275, 'column' => 9, 'source' => $incorrect],
        ['line' => 282, 'column' => 5, 'source' => $incorrect],
        ['line' => 290, 'column' => 9, 'source' => $incorrect],
        ['line' => 300, 'column' => 9, 'source' => $incorrect],
        ['line' => 308, 'column' => 9, 'source' => $incorrect],
        ['line' => 317, 'column' => 9, 'source' => $incorrect],
        ['line' => 318, 'column' => 9, 'source' => $incorrect],
        ['line' => 326, 'column' => 9, 'source' => $incorrect],
        ['line' => 335, 'column' => 5, 'source' => $closeBracket],
    ]);
});

it('reaches every scope-block and statement-boundary branch it documents', function (
    int $line,
    bool $reports
): void {
    $errors = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'failing.php')->getErrors();

    expect(array_key_exists($line, $errors))->toBe($reports);
})->with([
    'anonymous-class body is a skipped scope block' => [168, false],
    'the argument after an anonymous class is still checked' => [171, true],
    'an arrow-function body stays inside its statement' => [161, true],
    'the opening fragment of a multi-line string is code' => [177, true],
    'the tail lines of a multi-line string are content' => [178, false],
    'the opening fragment of a backtick string is code' => [224, true],
    'the tail lines of a backtick string are content' => [225, false],
    'the opening fragment of an interpolated string is code' => [231, true],
    'the tail lines of an interpolated string are content' => [232, false],
    'a comment does not exempt the line it shares with code' => [248, true],
    'the tail line of a block comment belongs to the comment' => [256, false],
    'the opening line of that comment is not measured either' => [255, false],
    'the tail line of a doc comment belongs to it the same way' => [262, false],
    'a one-line comment below another leaves its line to the code' => [269, true],
]);

it('converges with the sniff that rewrites multi-line strings', function (): void {
    $file = analyzeWithSniffs(
            [MULTI_LINE_STATEMENT_INDENT, 'CleanCode.Strings.MultilineStrings'],
            fixturePath('MultiLineStatementIndentSniff', 'passing.php')
        );

    expect($file->fixer->fixFile())->toBeTrue();
});

it('accepts every call with its arguments two levels in and its closer one, chained or not', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'calls.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports the old call layout with and without a chain', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'calls-old-layout.php');

    $incorrect = MULTI_LINE_STATEMENT_INDENT . '.IncorrectIndent';
    $closeBracket = MULTI_LINE_STATEMENT_INDENT . '.CloseBracketIndent';

    expect(violationTuples($file))->toBe([
        ['line' => 10, 'column' => 13, 'source' => $incorrect],
        ['line' => 11, 'column' => 13, 'source' => $incorrect],
        ['line' => 14, 'column' => 9, 'source' => $closeBracket],
        ['line' => 22, 'column' => 13, 'source' => $incorrect],
        ['line' => 23, 'column' => 13, 'source' => $incorrect],
        ['line' => 26, 'column' => 9, 'source' => $closeBracket],
        ['line' => 31, 'column' => 5, 'source' => $incorrect],
        ['line' => 32, 'column' => 5, 'source' => $incorrect],
        ['line' => 33, 'column' => 1, 'source' => $closeBracket],
        ['line' => 36, 'column' => 5, 'source' => $incorrect],
        ['line' => 37, 'column' => 1, 'source' => $closeBracket],
        ['line' => 40, 'column' => 5, 'source' => $incorrect],
        ['line' => 41, 'column' => 1, 'source' => $closeBracket],
        ['line' => 45, 'column' => 5, 'source' => $incorrect],
        ['line' => 46, 'column' => 1, 'source' => $closeBracket],
        ['line' => 50, 'column' => 5, 'source' => $incorrect],
        ['line' => 51, 'column' => 1, 'source' => $closeBracket],
        ['line' => 62, 'column' => 5, 'source' => $incorrect],
        ['line' => 63, 'column' => 1, 'source' => $closeBracket],
        ['line' => 71, 'column' => 5, 'source' => $incorrect],
        ['line' => 72, 'column' => 9, 'source' => $incorrect],
        ['line' => 73, 'column' => 5, 'source' => $closeBracket],
        ['line' => 75, 'column' => 5, 'source' => $incorrect],
        ['line' => 76, 'column' => 1, 'source' => $closeBracket],
        ['line' => 79, 'column' => 5, 'source' => $incorrect],
        ['line' => 80, 'column' => 9, 'source' => $incorrect],
        ['line' => 81, 'column' => 5, 'source' => $closeBracket],
        ['line' => 83, 'column' => 1, 'source' => $closeBracket],
        ['line' => 87, 'column' => 5, 'source' => $incorrect],
        ['line' => 88, 'column' => 1, 'source' => $closeBracket],
        ['line' => 93, 'column' => 5, 'source' => $incorrect],
        ['line' => 94, 'column' => 5, 'source' => $incorrect],
        ['line' => 95, 'column' => 1, 'source' => $closeBracket],
        ['line' => 107, 'column' => 5, 'source' => $incorrect],
        ['line' => 108, 'column' => 1, 'source' => $closeBracket],
        ['line' => 111, 'column' => 5, 'source' => $incorrect],
        ['line' => 112, 'column' => 1, 'source' => $closeBracket],
        ['line' => 115, 'column' => 5, 'source' => $incorrect],
        ['line' => 116, 'column' => 1, 'source' => $closeBracket],
        ['line' => 119, 'column' => 5, 'source' => $incorrect],
        ['line' => 120, 'column' => 1, 'source' => $closeBracket],
    ]);
});

it('rewrites the old call layout into the new one', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'calls-old-layout.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultiLineStatementIndentSniff', 'calls.php')));
});

it('accepts calls in the new layout inside closure and anonymous class bodies', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'closures.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reports the old call layout inside closure and anonymous class bodies', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'closures-old-layout.php');

    $incorrect = MULTI_LINE_STATEMENT_INDENT . '.IncorrectIndent';
    $closeBracket = MULTI_LINE_STATEMENT_INDENT . '.CloseBracketIndent';

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 13, 'source' => $incorrect],
        ['line' => 10, 'column' => 13, 'source' => $incorrect],
        ['line' => 12, 'column' => 17, 'source' => $incorrect],
        ['line' => 13, 'column' => 17, 'source' => $incorrect],
        ['line' => 14, 'column' => 17, 'source' => $closeBracket],
        ['line' => 16, 'column' => 13, 'source' => $incorrect],
        ['line' => 17, 'column' => 13, 'source' => $closeBracket],
        ['line' => 26, 'column' => 9, 'source' => $incorrect],
        ['line' => 27, 'column' => 5, 'source' => $closeBracket],
        ['line' => 34, 'column' => 5, 'source' => $incorrect],
        ['line' => 35, 'column' => 5, 'source' => $closeBracket],
        ['line' => 42, 'column' => 13, 'source' => $incorrect],
        ['line' => 43, 'column' => 9, 'source' => $closeBracket],
    ]);
});

it('rewrites the old call layout inside closure bodies into the new one', function (): void {
    $file = analyzeWithSniffs(
            [MULTI_LINE_STATEMENT_INDENT, 'Generic.WhiteSpace.ScopeIndent'],
            fixturePath('MultiLineStatementIndentSniff', 'closures-old-layout.php')
        );

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('MultiLineStatementIndentSniff', 'closures.php')));
});

it('indents each call from the line its own expression starts on', function (
    int $line,
    int $expected,
    int $found
): void {
    $errors = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'calls-old-layout.php')->getErrors();

    expect($errors)->toHaveKey($line);

    $message = current(current($errors[$line]))['message'];

    expect($message)->toContain("expected {$expected} spaces but found {$found}");
})->with([
    'argument of a call followed by a chain' => [10, 16, 12],
    'closer of a call followed by a chain' => [14, 12, 8],
    'argument of a call with no chain after it' => [22, 16, 12],
    'closer of a call with no chain after it' => [26, 12, 8],
    'argument of a chained call nested as an argument' => [72, 12, 8],
    'closer of a chained call nested as an argument' => [73, 8, 4],
    'closer of a call whose chain has a multi-line first link' => [63, 4, 0],
]);

it('keeps chain-link calls, hugging brackets and grouping parentheses where they were', function (int $line): void {
    $lines = file(fixturePath('MultiLineStatementIndentSniff', 'calls-old-layout.php'));

    expect(trim($lines[$line - 1]))->not->toBe('')
        ->and(analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'calls-old-layout.php')->getErrors())
        ->not->toHaveKey($line);
})->with([
    'argument of a multi-line first link' => [56],
    'closer of a multi-line first link' => [58],
    'argument of a multi-line link after a call' => [65],
    'closer of a multi-line link after a call' => [67],
    'chain below a call nested as an argument' => [74],
    'item of an array hugging the call it closes' => [98],
    'closer of an array hugging the call it closes' => [100],
    'closer of a closure hugging the call it closes' => [104],
    'item of a grouping parenthesis' => [123],
    'closer of a grouping parenthesis' => [124],
]);

it('anchors each line on the construct that owns it', function (int $failingLine, int $expected, int $found): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'failing.php');
    $errors = $file->getErrors();

    expect($errors)->toHaveKey($failingLine);

    $message = current(current($errors[$failingLine]))['message'];

    expect($message)->toContain("expected {$expected} spaces but found {$found}");
})->with([
    'sibling (boundary): boolean operand, opener shared with the first condition' => [25, 4, 8],
    'sibling: boolean operand, opener alone on its line' => [32, 4, 8],
    'sibling: array item' => [12, 4, 8],
    'continuation: concatenation below a wrapped argument' => [51, 12, 8],
    'continuation: arithmetic below a wrapped operand' => [58, 12, 8],
    'continuation: value below a trailing `=>`' => [19, 8, 4],
    'continuation: chain below its receiver' => [76, 12, 8],
    'continuation: arrow-function body below a trailing `=>`' => [146, 12, 8],
    'continuation: arrow-function body below a leading `=>`' => [153, 12, 8],
    'continuation: nullsafe chain below its receiver' => [186, 12, 8],
    'continuation: static chain below its receiver' => [191, 12, 8],
    'continuation: `instanceof` below its operand' => [217, 12, 8],
    'sibling: item of a nested index access' => [198, 12, 8],
    'sibling: `and` operand, opener alone on its line' => [206, 4, 8],
    'sibling: `or` operand, opener alone on its line' => [207, 4, 8],
    'sibling: `xor` operand, opener alone on its line' => [208, 4, 8],
    'sibling: member of a nested attribute group' => [239, 8, 4],
    'continuation: `??` below the operand it defaults' => [275, 12, 8],
    'continuation: value below a leading `=>`' => [282, 8, 4],
    'continuation: below a line that opens inside a comment' => [290, 12, 8],
    'sibling: argument of a statement starting inside a comment' => [300, 12, 8],
    'sibling: `||` operand, opener alone on its line' => [308, 4, 8],
    'continuation: ternary consequent below its operand' => [317, 12, 8],
    'continuation: ternary alternative below its operand' => [318, 12, 8],
    'sibling: argument of a call nested in a call' => [326, 16, 8],
]);

it('reaches every member of its hand-maintained token arrays', function (
    int $passingLine,
    int $failingLine,
    string $construct
): void {
    $lineOf = static function (string $fixture, int $line): string {
        $lines = file(fixturePath('MultiLineStatementIndentSniff', $fixture));

        return trim($lines[$line - 1]);
    };

    expect($lineOf('passing.php', $passingLine))->toContain($construct)
        ->and($lineOf('failing.php', $failingLine))->toContain($construct);

    expect(analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'passing.php')->getErrors())
        ->not->toHaveKey($passingLine)
        ->and(analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'failing.php')->getErrors())
        ->toHaveKey($failingLine);
})->with([
    'CHAIN_OPERATORS: T_OBJECT_OPERATOR' => [85, 76, '->prepare()'],
    'CHAIN_OPERATORS: T_NULLSAFE_OBJECT_OPERATOR' => [209, 186, '?->getProfile()'],
    'CHAIN_OPERATORS: T_DOUBLE_COLON' => [215, 191, "::make('first')"],
    'BRACKET_OPENERS: T_OPEN_PARENTHESIS' => [364, 326, '$value'],
    'BRACKET_OPENERS: T_OPEN_SQUARE_BRACKET' => [223, 198, '$key'],
    'BRACKET_OPENERS: T_OPEN_SHORT_ARRAY' => [52, 90, "'flag' => true,"],
    'BRACKET_OPENERS: T_ATTRIBUTE' => [267, 239, 'Route('],
    'SIBLING_OPERATORS: T_BOOLEAN_AND' => [36, 32, '&& $second === 2'],
    'SIBLING_OPERATORS: T_BOOLEAN_OR' => [346, 308, '|| $second === 2'],
    'SIBLING_OPERATORS: T_LOGICAL_AND' => [232, 206, 'and $second === 2'],
    'SIBLING_OPERATORS: T_LOGICAL_OR' => [233, 207, 'or $third === 3'],
    'SIBLING_OPERATORS: T_LOGICAL_XOR' => [234, 208, 'xor $fourth === 4'],
    'continuationTokens(): T_INLINE_THEN' => [355, 317, "? 'active'"],
    'continuationTokens(): T_INLINE_ELSE' => [356, 318, ": 'inactive'"],
    'continuationTokens(): T_INSTANCEOF' => [243, 217, 'instanceof Probe'],
    'delegated to a union: T_COALESCE' => [306, 275, '?? $fallback'],
    'delegated to a union: T_DOUBLE_ARROW' => [313, 282, "=> 'App\\Http\\Controllers\\HomeController'"],
    'BRACKET_OPENERS: T_OPEN_USE_GROUP' => [375, 335, '};'],
    'UNLINKED_PAIRS: T_CLOSE_USE_GROUP' => [375, 335, '};'],
]);

it('accounts for every scope opener PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/WhiteSpace/MultiLineStatementIndentSniff.php';

    $family = [];

    foreach (\PHP_CodeSniffer\Util\Tokens::$scopeOpeners as $code) {
        $family[] = is_int($code) === true
            ? (string) token_name($code)
            : (string) preg_replace('/^PHPCS_/', '', $code);
    }

    $tokens = analyzeStdinSource(
            [MULTI_LINE_STATEMENT_INDENT],
            "<?php\n\n\$double = fn (\$value) => \$value * 2;\n"
        )->getTokens();
    $arrows = array_values(array_filter($tokens, static fn (array $token): bool => $token['code'] === T_FN));

    expect(in_array('T_FN', $family, true))->toBeFalse('T_FN is still missing from the register')
        ->and($arrows)->toHaveCount(1)
        ->and($arrows[0])->toHaveKey('scope_closer');

    $family[] = 'T_FN';
    sort($family);

    $included = tokenNamesInConstant($path, 'EXPRESSION_SCOPES', [MULTI_LINE_STATEMENT_INDENT]);
    $excluded = tokenNamesInConstant($path, 'NON_EXPRESSION_SCOPES', [MULTI_LINE_STATEMENT_INDENT]);
    $accounted = array_merge($included, $excluded);
    sort($accounted);

    expect($included)->not->toBeEmpty('EXPRESSION_SCOPES was found and read')
        ->and($excluded)->not->toBeEmpty('NON_EXPRESSION_SCOPES was found and read')
        ->and(array_values(array_intersect($included, $excluded)))
        ->toBe([], 'no token is both an expression scope and not one')
        ->and(array_values(array_diff($family, $accounted)))
        ->toBe([], 'every scope opener PHPCS defines is accounted for')
        ->and(array_values(array_diff($accounted, $family)))
        ->toBe([], 'nothing is accounted for that PHPCS does not define as a scope opener');
});

it('reads a long comment inside a statement in linear time', function (): void {
    $size = 10000;
    $source = "<?php\n\ndoSomething(\n        /* explain\n"
        . str_repeat("           filler\n", $size)
        . "           done */ \$flag,\n    );\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-comment-scale-', true) . '.php';
    file_put_contents($path, $source);

    $sniff = sniffInstance(MULTI_LINE_STATEMENT_INDENT);
    $before = $sniff->scanCounts();

    try {
        $file = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path);
    } finally {
        unlink($path);
    }

    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect($file->getErrorCount())->toBe(0, 'the comment holds its own lines')
        ->and($counted['commentStaysOpen.evaluations'])->toBe(
            ((2 * $size) + 10),
            'the open state is carried along two single passes, not replayed per line'
        );
});

it('anchors lines on a long comment\'s opening line in linear time', function (): void {
    $size = 10000;
    $source = "<?php\n\n/** explain\n"
        . str_repeat(" * filler\n", $size)
        . " */ \$result = \$queryBuilder\n    ->select('*')\n    ->from('users');\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-anchor-scale-', true) . '.php';
    file_put_contents($path, $source);

    $sniff = sniffInstance(MULTI_LINE_STATEMENT_INDENT);
    $before = $sniff->scanCounts();

    try {
        $file = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path);
    } finally {
        unlink($path);
    }

    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect($file->getErrorCount())->toBe(0, 'the chain hangs below the line the comment opened')
        ->and($counted['lineFirstToken.readings'])->toBe(
            5,
            'the three chain lines and the statement start are read once each, whatever the comment costs'
        )
        ->and($counted['lineFirstToken.commentHops'])->toBe(
            3,
            'each line that opens inside the comment reaches its opening line in one step'
        )
        ->and($counted['lineStart.steps'])->toBe(
            16,
            'the 5 readings and 3 hops examine 16 tokens between them — two each, from the map,'
                . ' never a line walked back along nor a comment replayed'
        )
        ->and($counted['commentStaysOpen.evaluations'])->toBe(
            ((5 * $size) + 20),
            'the map asks the open/closed question of each doc-comment token once'
        );
});

it('anchors sibling lines on a long opener line in linear time', function (): void {
    $size = 4000;
    $operands = [];
    $items = '';

    for ($i = 0; $i < $size; $i++) {
        $operands[] = '$operand' . $i;
        $items .= "    \$item{$i},\n";
    }

    $source = "<?php\n\n\$total = [" . implode(', ', $operands) . "] + [\n" . $items . "];\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-opener-scale-', true) . '.php';
    file_put_contents($path, $source);

    $sniff = sniffInstance(MULTI_LINE_STATEMENT_INDENT);
    $before = $sniff->scanCounts();

    try {
        $file = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path);
    } finally {
        unlink($path);
    }

    $counted = cacheCountsDelta($before, $sniff->scanCounts());

    expect($file->getErrorCount())->toBe(0, 'every item sits a level in from the line the second `[` opens on')
        ->and($counted['lineFirstToken.readings'])->toBe(
            ((2 * $size) + 3),
            'each of the wrapped lines is read once as itself and once as the anchor it hangs on'
        )
        ->and($counted['lineStart.steps'])->toBe(
            ((4 * $size) + 6),
            'the 2n+3 readings examine 4n+6 tokens between them — two each, from the map, not the'
                . ' whole line the opener sits at the end of'
        )
        ->and($counted['lineFirstToken.commentHops'])->toBe(
            0,
            'nothing here opens inside a comment'
        );
});

it('stays silent on this package\'s own source', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach ([$root . '/CleanCode', $root . '/tests'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $isFixture = str_contains($path, '/fixtures/');

            if ($file->isFile() === true && $file->getExtension() === 'php' && $isFixture === false) {
                $files[] = $path;
            }
        }
    }

    expect($files)->not->toBeEmpty();

    $offenders = [];

    foreach ($files as $path) {
        $errors = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path)->getErrors();

        foreach (array_keys($errors) as $line) {
            $offenders[] = substr($path, strlen($root) + 1) . ':' . $line;
        }
    }

    expect($offenders)->toBe([]);
});
