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
 * chains, array literals, argument lists, boolean conditions with the opener
 * both shared and alone on its line, concatenation, arithmetic, ternaries,
 * nested brackets and chains, a deeper base indent — plus the near-miss shapes
 * it must stay silent on: closure and match bodies (scope-indent rules own
 * those), heredoc and nowdoc bodies, attribute groups, and single-line
 * statements.
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
    ]);
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
