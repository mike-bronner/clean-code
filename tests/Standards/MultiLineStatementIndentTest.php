<?php

/**
 * Tests the custom CleanCode.WhiteSpace.MultiLineStatementIndent sniff.
 *
 * The sniff decides one thing per line: which earlier line that line hangs
 * below. Two anchors exist, and every case here pins which of them applies:
 *
 * - a *sibling* line — an argument, an array item, or a condition led by a
 *   boolean operator — hangs one level below the line its enclosing construct
 *   opens on;
 * - a *continuation* line — one led by (or sitting below) any other binary or
 *   ternary operator, or by a chain operator — hangs one level below the line
 *   the expression it continues started on, which inside a bracket is the
 *   element's own line rather than the opener's.
 *
 * Boolean operators are siblings because
 * `CleanCode.Conditionals.OneConditionPerLine` puts each top-level condition
 * on its own line: they are peers of the first condition, not a continuation
 * of it. Concatenation and arithmetic continue one expression, so they sit a
 * level deeper. Both shapes appear throughout this package's own source, and
 * the sniff reports nothing on it — that whole-tree silence is what the
 * `stays silent on this package's own source` test below pins, because an
 * anchor picked wrongly does not merely under-report: `phpcbf` rewrites
 * compliant code to match it.
 */

declare(strict_types=1);

const MULTI_LINE_STATEMENT_INDENT = 'CleanCode.WhiteSpace.MultiLineStatementIndent';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MULTI_LINE_STATEMENT_INDENT);
});

/**
 * passing.php carries the compliant form of every construct the sniff walks —
 * chains in all three of their operators (`->`, `?->`, `::`), array literals,
 * index access, argument lists, boolean conditions in both their symbol and
 * keyword forms with the opener shared and alone on its line, concatenation,
 * arithmetic, `instanceof`, ternaries, nested brackets and chains, a deeper
 * base indent — plus the near-miss shapes it must stay silent on: closure,
 * anonymous-class, and match bodies (scope-indent rules own those), heredoc
 * and nowdoc bodies, the tail lines of a quoted string that spans lines in
 * each form PHPCS tokenizes separately (plain, interpolated, backtick),
 * attribute groups both at statement level and nested in a parameter list, a
 * comment sharing its line with code, a comment running onto the line below in
 * each shape PHPCS splits per physical line (block, doc, a body line opening
 * with a slash pair, an opening line that is a bare slash-star-slash), and
 * single-line statements. Arrow
 * functions appear in all three positions their `=>` can take — trailing,
 * leading, and at statement level — because that arrow is a distinct token
 * from an array's.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MULTI_LINE_STATEMENT_INDENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every violation in failing.php, at the line and column PHPCS reports it and
 * under the code that says which anchor was missed. The list is exhaustive:
 * a new false positive shows up as an extra tuple, and a construct the sniff
 * stops checking as a missing one.
 */
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
        ['line' => 39, 'column' => 1, 'source' => $incorrect],
        ['line' => 45, 'column' => 1, 'source' => $incorrect],
        ['line' => 51, 'column' => 5, 'source' => $incorrect],
        ['line' => 58, 'column' => 5, 'source' => $incorrect],
        ['line' => 63, 'column' => 9, 'source' => $incorrect],
        ['line' => 64, 'column' => 9, 'source' => $incorrect],
        ['line' => 70, 'column' => 5, 'source' => $incorrect],
        ['line' => 76, 'column' => 5, 'source' => $incorrect],
        ['line' => 84, 'column' => 3, 'source' => $closeBracket],
        ['line' => 90, 'column' => 5, 'source' => $incorrect],
        ['line' => 92, 'column' => 1, 'source' => $closeBracket],
        ['line' => 98, 'column' => 1, 'source' => $incorrect],
        ['line' => 105, 'column' => 1, 'source' => $incorrect],
        ['line' => 113, 'column' => 1, 'source' => $incorrect],
        ['line' => 121, 'column' => 3, 'source' => $closeBracket],
        ['line' => 131, 'column' => 1, 'source' => $incorrect],
        ['line' => 136, 'column' => 1, 'source' => $incorrect],
        ['line' => 146, 'column' => 5, 'source' => $incorrect],
        ['line' => 153, 'column' => 5, 'source' => $incorrect],
        ['line' => 161, 'column' => 1, 'source' => $incorrect],
        ['line' => 171, 'column' => 1, 'source' => $incorrect],
        ['line' => 177, 'column' => 1, 'source' => $incorrect],
        ['line' => 186, 'column' => 5, 'source' => $incorrect],
        ['line' => 191, 'column' => 5, 'source' => $incorrect],
        ['line' => 198, 'column' => 5, 'source' => $incorrect],
        ['line' => 206, 'column' => 9, 'source' => $incorrect],
        ['line' => 207, 'column' => 9, 'source' => $incorrect],
        ['line' => 208, 'column' => 9, 'source' => $incorrect],
        ['line' => 217, 'column' => 5, 'source' => $incorrect],
        ['line' => 224, 'column' => 1, 'source' => $incorrect],
        ['line' => 231, 'column' => 1, 'source' => $incorrect],
        ['line' => 239, 'column' => 5, 'source' => $incorrect],
        ['line' => 248, 'column' => 3, 'source' => $incorrect],
        ['line' => 269, 'column' => 1, 'source' => $incorrect],
        ['line' => 275, 'column' => 5, 'source' => $incorrect],
        ['line' => 282, 'column' => 5, 'source' => $incorrect],
        ['line' => 290, 'column' => 5, 'source' => $incorrect],
        ['line' => 300, 'column' => 5, 'source' => $incorrect],
        ['line' => 308, 'column' => 9, 'source' => $incorrect],
        ['line' => 317, 'column' => 5, 'source' => $incorrect],
        ['line' => 318, 'column' => 5, 'source' => $incorrect],
        ['line' => 326, 'column' => 5, 'source' => $incorrect],
    ]);
});

/**
 * Three constructs the sniff names in its own docblock as deliberate
 * decisions, each pinned by the pair of lines the exhaustive list above
 * already carries — a fixture reaching the construct compliantly in
 * passing.php, and the near miss in failing.php. Named here because the list
 * alone does not say *which* branch each tuple defends, and each of these
 * branches was previously load-bearing with no fixture reaching it at all.
 *
 * - **anonymous class** (`EXPRESSION_SCOPES`): its body is a scope block and
 *   is skipped whole — no tuple falls between 165 and 170 — while the
 *   argument after it (171) is still checked. Dropping `T_ANON_CLASS` from
 *   the constant makes the body lines report.
 * - **arrow function** (the `T_FN` exception in `findStatementEnd()`): the
 *   body stays inside the statement, so an un-indented one (161) reports.
 *   Dropping the exception ends the statement at the arrow, splitting it into
 *   two single-line fragments that both evade the multi-line check.
 * - **multi-line quoted string**: only its *tail* lines are content. The
 *   opening fragment is the argument and reports when under-indented (177);
 *   the tail (178) never does, so no tuple names it.
 * - **backtick string** (`RAW_CONTENT`'s `T_ENCAPSED_AND_WHITESPACE`): the one
 *   quoted string PHPCS still splits into that token. Same split as above —
 *   opener (224) reports, tail (225) does not.
 * - **interpolated multi-line string** (`STRING_LITERALS`'s
 *   `T_DOUBLE_QUOTED_STRING`): interpolation changes only which token PHPCS
 *   splits the string into, not that the tail is the string's own value.
 *   Opener (231) reports, tail (232) does not. Dropping the member does not
 *   merely add a false positive — it deadlocks `phpcbf`, exactly as the plain
 *   string did, which is what the convergence test below guards.
 * - **inline leading comment**: a comment never exempts the line it opens, so
 *   a misindented line that starts with one still reports (248) — at the
 *   comment's own column, because that is where the line's indent is.
 * - **comment running onto the line below**: the same per-physical-line split
 *   the strings above get, in the one other construct PHPCS splits that way.
 *   The code sharing the comment's *tail* line follows the comment's own body
 *   rather than the line's indent, so that line is the comment's and is left
 *   alone — in the block form (256) and the doc form (262) alike. Neither the
 *   tail nor the misindented opening line above it reports, because a comment
 *   line is never measured here; under the shipped `rules.xml` that opening
 *   line is `PSR2.Methods.FunctionCallSignature.Indent`'s to report, the same
 *   division of labour the string case has with the multi-line-string sniff.
 * - **a line that opens inside a comment measures nowhere on itself**: what
 *   stands in front of its code is the comment's body, so every reading of that
 *   line — as an anchor for the continuation below it (290), and as a
 *   statement's own base indent when the statement starts there (300) — comes
 *   from the line the comment opened. Measure it where it sits and both report
 *   against an indent of 0, which is where the tail fragment begins.
 * - **a whole one-line comment below another**: the near miss for the above.
 *   It opens *and closes* on its own line, so it holds nothing and the code
 *   after it is the line's own — a misindented one still reports (269). Hold
 *   that line too and this tuple disappears while nothing else changes.
 */
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

/**
 * The cost of getting the string case wrong is not a false positive, it is a
 * fixer that never converges: padding injected into a string's value changes
 * nothing the sniff measures, so it re-reports on the next pass until `phpcbf`
 * gives up with `FAILED TO FIX` — taking every *other* sniff's fixes in the
 * file down with it. `fixFile()` returns false exactly in that case.
 *
 * Run with `CleanCode.Strings.MultilineStrings` active because that is the
 * sniff which actually rewrites the shape, and the pair is what deadlocked:
 * one sniff converting the string to a heredoc while the other re-indented it.
 */
it('converges with the sniff that rewrites multi-line strings', function (): void {
    $file = analyzeWithSniffs(
        [MULTI_LINE_STATEMENT_INDENT, 'CleanCode.Strings.MultilineStrings'],
        fixturePath('MultiLineStatementIndentSniff', 'passing.php')
    );

    expect($file->fixer->fixFile())->toBeTrue();
});

/**
 * The two anchors, pinned as pairs: the compliant line in passing.php is not
 * flagged, and the *other* anchor's indent for the same construct in
 * failing.php is. Without both halves a sniff that anchored everything on the
 * opener, or everything on the expression start, would still pass one of them.
 *
 * The `sibling` cases are the layout review found `phpcbf` corrupting in this
 * package's own source (a boolean operand under an opener alone on its line,
 * rewritten 12 -> 16 spaces); the `continuation` cases are the layout the fix
 * for that first corrupted in the other direction (a concatenation under a
 * long argument, rewritten 16 -> 12).
 *
 * One row is a boundary rather than a discriminator, and is labelled as such:
 * `boolean operand, opener shared` reports the same expectation under either
 * anchor, because a first condition sharing the opener's line puts the two
 * anchors on the same line. That is exactly why the bug hid — every fixture
 * this sniff shipped with used that layout — so the row stays, pinning the
 * agreement, while the `opener alone` row below it is the one that fails when
 * the anchor is wrong.
 */
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
    'continuation: concatenation below a wrapped argument' => [51, 8, 4],
    'continuation: arithmetic below a wrapped operand' => [58, 8, 4],
    'continuation: value below a trailing `=>`' => [19, 8, 4],
    'continuation: chain below its receiver' => [76, 8, 4],
    'continuation: arrow-function body below a trailing `=>`' => [146, 8, 4],
    'continuation: arrow-function body below a leading `=>`' => [153, 8, 4],
    'continuation: nullsafe chain below its receiver' => [186, 8, 4],
    'continuation: static chain below its receiver' => [191, 8, 4],
    'continuation: `instanceof` below its operand' => [217, 8, 4],
    'sibling: item of a nested index access' => [198, 8, 4],
    'sibling: `and` operand, opener alone on its line' => [206, 4, 8],
    'sibling: `or` operand, opener alone on its line' => [207, 4, 8],
    'sibling: `xor` operand, opener alone on its line' => [208, 4, 8],
    'sibling: member of a nested attribute group' => [239, 8, 4],
    'continuation: `??` below the operand it defaults' => [275, 8, 4],
    'continuation: value below a leading `=>`' => [282, 8, 4],
    'continuation: below a line that opens inside a comment' => [290, 8, 4],
    'sibling: argument of a statement starting inside a comment' => [300, 8, 4],
    'sibling: `||` operand, opener alone on its line' => [308, 4, 8],
    'continuation: ternary consequent below its operand' => [317, 8, 4],
    'continuation: ternary alternative below its operand' => [318, 8, 4],
    'sibling: argument of a call nested in a call' => [326, 8, 4],
]);

/**
 * One row per *anchoring* member of the sniff's hand-maintained token arrays,
 * each naming the compliant line that reaches it and the near miss that
 * reports.
 *
 * These arrays are where this sniff's defects keep landing: a member is added,
 * no fixture reaches it, and whether it is load-bearing stays unknown until a
 * rewrite silently drops it. Every row is a mutation trap — delete the named
 * member and the compliant line starts reporting while the failing line stops,
 * so neither half can pass by luck. Each pair uses the layout where the two
 * anchors actually differ, because the obvious layout for most of these
 * constructs puts both anchors on one line and discriminates nothing.
 *
 * The last two rows are not members of a hand-maintained array at all: they are
 * the two tokens `continuationTokens()`'s docblock names as deliberately left
 * to a PHPCS union, which is the same coverage hole wearing the opposite face —
 * a member nobody maintains is a member nobody fixtures either. Their mutation
 * is a union that stops carrying the token: `T_DOUBLE_ARROW` has only
 * `Tokens::$assignmentTokens`, so dropping it there is enough, while
 * `T_COALESCE` sits in `Tokens::$operators` *and* `Tokens::$comparisonTokens`
 * and takes both. Each row still swaps its pair either way.
 *
 * Three kinds of member are deliberately elsewhere. The ones whose job is to
 * *suppress* a line rather than anchor it — `RAW_CONTENT`, `STRING_LITERALS`,
 * and `EXPRESSION_SCOPES` — are pinned by the branch test above, since for
 * those the compliant and failing lines are both silent and it is the
 * neighbouring line that reports. The ones the anchor test above already names
 * as a pair — `T_STRING_CONCAT`, and `T_FN_ARROW` in both the trailing and the
 * leading position — are pinned by those rows, which are the same mutation trap
 * under another name. And an unreachable member is pinned by its docblock
 * instead of a fixture: `T_MATCH_ARROW` and `T_INLINE_HTML` both say why in the
 * sniff. No member appears in two arrays at once — whichever copy
 * checkLine() reads first would answer for both, leaving neither testable,
 * which is why `continuationTokens()` no longer repeats CHAIN_OPERATORS or
 * anything `Tokens::$operators` already carries.
 */
it('reaches every member of its hand-maintained token arrays', function (
    int $passingLine,
    int $failingLine,
    string $construct
): void {
    $lineOf = static function (string $fixture, int $line): string {
        $lines = file(fixturePath('MultiLineStatementIndentSniff', $fixture));

        return trim($lines[$line - 1]);
    };

    // Both fixtures reach the member, at the one indent apart that tells the
    // sibling anchor from the continuation one.
    expect($lineOf('passing.php', $passingLine))->toContain($construct)
        ->and($lineOf('failing.php', $failingLine))->toContain($construct);

    // The compliant one is silent and the near miss reports; delete the member
    // from the sniff and the two swap.
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
]);

/**
 * Deciding whether a comment fragment continues the one above it is a question
 * about the fragment before it, and answering it by scanning back to the start
 * of the comment costs one pass per line — so a comment of n lines inside a
 * statement costs O(n^2). checkStatement() carries the state forward instead,
 * which is the only reason this shape stays usable: a commented-out block
 * inside one call is ordinary code, not an adversarial input.
 *
 * The fixture is generated for the same reason the sibling scale test in
 * `ArrayAccessorsTest.php` generates its own — the shapes only separate a
 * linear implementation from a quadratic one in the thousands, and a committed
 * 10,000-line file is a worse thing for this repository to carry.
 *
 * The budget is wall clock and set generously against measured numbers: the
 * carried state answers n=10,000 in about a third of a second here, while
 * scanning back needs 11s for the same file — so the assertion has an order of
 * magnitude of headroom on a loaded runner and still cannot pass against a
 * rescan by being lucky.
 */
it('reads a long comment inside a statement in linear time', function (): void {
    $size = 10000;
    $source = "<?php\n\ndoSomething(\n    /* explain\n"
        . str_repeat("       filler\n", $size)
        . "       done */ \$flag,\n);\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-comment-scale-', true) . '.php';
    file_put_contents($path, $source);

    try {
        $startedAt = hrtime(true);
        $file = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path);
        $elapsed = (hrtime(true) - $startedAt) / 1e9;
    } finally {
        unlink($path);
    }

    expect($file->getErrorCount())->toBe(0, 'the comment holds its own lines')
        ->and($elapsed)->toBeLessThan(3.0, "a {$size}-line comment took {$elapsed}s");
});

/**
 * The same cost in the other reader of that state. A line opening inside a
 * comment is measured on the line the comment opened, so every reading of it —
 * the statement's own base indent, and the anchor under each continuation line
 * below it — asks how far back that comment began. Recovering the answer by
 * replaying the comment costs one pass per reading, and each pass is the whole
 * comment: quadratic again, and this time also one stack frame per line.
 * mapCommentOpeners() answers it in one lookup.
 *
 * The shape is deliberately the one the sibling test above cannot reach: there
 * the comment sits inside an argument list, where the comma resets the anchor
 * before any line has to be measured back through it. Here the statement itself
 * *starts* on the line the comment closes on, and a chain hangs below it, so
 * every line measured runs back through the comment.
 *
 * Same generated fixture and same generous budget as above: the carried state
 * answers n=10,000 in about a tenth of a second here, while replaying needs
 * upwards of a minute.
 */
it('anchors lines on a long comment\'s opening line in linear time', function (): void {
    $size = 10000;
    $source = "<?php\n\n/** explain\n"
        . str_repeat(" * filler\n", $size)
        . " */ \$result = \$queryBuilder\n    ->select('*')\n    ->from('users');\n";

    $path = sys_get_temp_dir() . '/' . uniqid('cleancode-anchor-scale-', true) . '.php';
    file_put_contents($path, $source);

    try {
        $startedAt = hrtime(true);
        $file = analyzeWithSniffs([MULTI_LINE_STATEMENT_INDENT], $path);
        $elapsed = (hrtime(true) - $startedAt) / 1e9;
    } finally {
        unlink($path);
    }

    expect($file->getErrorCount())->toBe(0, 'the chain hangs below the line the comment opened')
        ->and($elapsed)->toBeLessThan(3.0, "a {$size}-line comment took {$elapsed}s");
});

/**
 * The reason this sniff was escalated: an auto-fixer that mis-anchors a line
 * does not merely report a false positive, it *rewrites* compliant code. This
 * package's own source is written to the standard, so the sniff has to be
 * silent across all of it — every sniff, helper, and test file, with the
 * fixtures excluded because they are deliberately non-compliant.
 *
 * Asserted through the sniff itself rather than a `phpcs` subprocess so the
 * failure names the file and line; `tests/Contract/ShippedPackageSmokeTest.php`
 * covers the shipped-binary direction.
 */
it('stays silent on this package\'s own source', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach ([$root . '/CleanCode/Sniffs', $root . '/CleanCode/Support', $root . '/tests'] as $directory) {
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
