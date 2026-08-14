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
 * comment sharing its line with code, and single-line statements. Arrow
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
 * Two kinds of member are deliberately elsewhere. The ones whose job is to
 * *suppress* a line rather than anchor it — `RAW_CONTENT` and
 * `STRING_LITERALS` — are pinned by the raw-content test below, since for
 * those the compliant and failing lines are both silent and it is the
 * neighbouring opener that reports. And an unreachable member is pinned by its
 * docblock instead of a fixture: `T_MATCH_ARROW` and `T_INLINE_HTML` both say
 * why in the sniff. No member appears in two arrays at once — whichever copy
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
    'BRACKET_OPENERS: T_OPEN_SQUARE_BRACKET' => [223, 198, '$key'],
    'BRACKET_OPENERS: T_ATTRIBUTE' => [267, 239, 'Route('],
    'SIBLING_OPERATORS: T_LOGICAL_AND' => [232, 206, 'and $second === 2'],
    'SIBLING_OPERATORS: T_LOGICAL_OR' => [233, 207, 'or $third === 3'],
    'SIBLING_OPERATORS: T_LOGICAL_XOR' => [234, 208, 'xor $fourth === 4'],
    'continuationTokens(): T_INSTANCEOF' => [243, 217, 'instanceof Probe'],
]);

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
