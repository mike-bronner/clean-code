<?php

/**
 * Tests the custom CleanCode.ClearCode.SectionComment sniff (Clear Code:
 * Encapsulate Each Concept in a Method, #13, partial enforcement per #159).
 * Fixtures live in tests/fixtures/SectionCommentSniff/: every near-miss shape
 * in passing.php, every reported shape in failing.php, the two exclusion lists
 * in configured.php, and the PHP 8.4 hook limit in property-hooks.php. The
 * rule is detection-only, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const SECTION_COMMENT = 'CleanCode.ClearCode.SectionComment';

const SECTION_COMMENT_WARNING = SECTION_COMMENT . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SECTION_COMMENT);
});

/**
 * Every shape the rule must stay silent on. Each group pins one of the sniff's
 * early returns, and a false positive on any of them makes an advisory rule
 * unusable in a real codebase:
 *
 * - line 13, a file-level comment followed by a statement, and line 18, a
 *   class-level comment between members — neither sits in a function body.
 * - lines 21-23 and the other docblocks, which tokenize as T_DOC_COMMENT_* and
 *   so never reach a sniff registered on T_COMMENT at all.
 * - line 28, a comment trailing a statement, and line 32, a one-line block
 *   comment sharing its line with the statement after it. A comment that
 *   shares a line annotates that line; it labels no block.
 * - lines 39-41, a block comment spanning two physical lines. PHP_CodeSniffer
 *   splits it into one T_COMMENT per line, so without the self-contained check
 *   the continuation line reads as a whole comment on its own line — the
 *   tokenizer quirk this fixture exists for.
 * - line 52, a comment with nothing but blank lines and the closing brace of
 *   its method after it, and line 62, the same at the end of a nested `if`
 *   whose method continues afterwards. Neither introduces a block.
 * - lines 68, 71, 74 and 77, the four debt markers, and lines 85, 88 and 91,
 *   the three formatter directives — all owned by sibling standards.
 * - line 97, a `phpcs:ignore` annotation. It names another sniff, so the
 *   suppression cannot account for this rule's silence: the tokenizer gives
 *   the annotation its own type rather than T_COMMENT.
 * - lines 104 and 106, comments inside an array literal, and line 116, one
 *   inside an argument list. What follows each is an element, not a statement
 *   in the enclosing scope.
 * - line 124, a comment inside an anonymous class declared in a method, and
 *   line 133, one above a `match` arm. Both still carry T_FUNCTION in their
 *   conditions, so reading the whole chain rather than the innermost scope
 *   would report both.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every section label is flagged, once, at its own line:
 *
 * - line 17, straight after the opening brace; line 27, after a statement;
 *   line 39, after a nested block. The three statement boundaries a label can
 *   follow.
 * - line 47, a `#` comment, and line 55, a one-line `/` `* … *` `/` comment —
 *   the two spellings besides `//` that tokenize as T_COMMENT.
 * - line 63, the head of a two-line comment run. Only the head reports: the
 *   run is one label for one block, and line 64 is deliberately absent below.
 * - lines 72 and 74, a run split by a blank line. A blank line ends a run, so
 *   both halves are labels of their own — the pair that distinguishes
 *   "previous token is a comment" from "previous comment is directly above".
 * - line 82, a label separated from its block by two blank lines. Blank lines
 *   are ignored when looking for the following statement.
 * - lines 93, 103, 115 and 125 — a closure, an `if`, a `foreach` and a `try`.
 *   The last three are control structures nested inside a method, which the
 *   scope walk has to pass through to reach the function that owns them.
 */
it('flags every section label at its own line with the expected code', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            17 => [SECTION_COMMENT_WARNING],
            27 => [SECTION_COMMENT_WARNING],
            39 => [SECTION_COMMENT_WARNING],
            47 => [SECTION_COMMENT_WARNING],
            55 => [SECTION_COMMENT_WARNING],
            63 => [SECTION_COMMENT_WARNING],
            72 => [SECTION_COMMENT_WARNING],
            74 => [SECTION_COMMENT_WARNING],
            82 => [SECTION_COMMENT_WARNING],
            93 => [SECTION_COMMENT_WARNING],
            103 => [SECTION_COMMENT_WARNING],
            115 => [SECTION_COMMENT_WARNING],
            125 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * Each warning is reported at the comment itself, so an editor's inline marker
 * sits under the label rather than under the block it introduces. The columns
 * are asserted for one line per indentation depth: line 17 sits in a method
 * body, line 93 one level deeper inside a closure.
 */
it('reports at the comment rather than the statement it introduces', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 17, 'column' => 9, 'source' => SECTION_COMMENT_WARNING])
        ->toContain(['line' => 93, 'column' => 13, 'source' => SECTION_COMMENT_WARNING]);
});

/**
 * The message quotes the offending comment and names the standard, so a
 * developer reading the report knows both which label to remove and why. The
 * `#` spelling is asserted alongside the `//` one because the quoted text is
 * the comment's own source, trimmed but not otherwise rewritten.
 */
it('quotes the comment and names the standard in the warning message', function (): void {
    $warnings = analyzeFixture(SECTION_COMMENT, 'failing.php')->getWarnings();

    expect($warnings[17][9][0]['message'])
        ->toContain('// Validate the payload.')
        ->toContain('extract the block it introduces into a method named after it')
        ->toContain('docs/standards/clear-code-encapsulate-each-concept-in-a-method.md')
        ->and($warnings[47][9][0]['message'])->toContain('# Normalise the keys.')
        ->and($warnings[55][9][0]['message'])->toContain('/* Normalise the keys. */');
});

/**
 * Both exclusion lists are public sniff properties, as the standard's doc
 * advertises, and one fixture pins both directions for each. Under the shipped
 * defaults only the unmarked label (line 33) reports, while the debt marker
 * (line 17) and the formatter directive (line 25) are silent. Emptying either
 * list makes the comment it owned report; pointing either list at a word the
 * unmarked label contains silences that label instead and lets the comment the
 * list used to own through. A property that was ignored would leave every run
 * identical to the first.
 */
it('exposes configurable debt-marker and formatter-directive lists', function (): void {
    expect(array_keys(analyzeFixture(SECTION_COMMENT, 'configured.php')->getWarnings()))
        ->toBe([33]);

    $withoutMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = [];
    });

    expect(array_keys($withoutMarkers->getWarnings()))->toBe([17, 33]);

    $withoutDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = [];
    });

    expect(array_keys($withoutDirectives->getWarnings()))->toBe([25, 33]);

    $retunedMarkers = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->debtMarkers = ['Normalise'];
    });

    expect(array_keys($retunedMarkers->getWarnings()))->toBe([17]);

    $retunedDirectives = analyzeFixture(SECTION_COMMENT, 'configured.php', static function (object $sniff): void {
        $sniff->formatterDirectives = ['Normalise'];
    });

    expect(array_keys($retunedDirectives->getWarnings()))->toBe([25]);
});

/**
 * The debt markers are matched on word boundaries rather than as substrings,
 * so a comment that merely contains the letters is still a section label. Both
 * halves are asserted in one run: line 17's `Hack` is the marker in another
 * casing and is silent, while line 20's `Unshackle` contains the same four
 * letters mid-word and reports. Dropping the boundaries from the pattern
 * silences line 20 and fails this test; dropping the exclusion altogether
 * reports line 17 and fails it too.
 */
it('matches debt markers as words rather than as substrings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'marker-substring.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            20 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * PHP 8.4 property hooks are the one construct that bears no scope of its own:
 * PHP_CodeSniffer gives a hook body no scope opener, so its comments carry the
 * class as their innermost condition and read exactly like a comment between
 * class members. The rule stays silent on them, which is recorded here as a
 * known limit rather than left to look like coverage. The ordinary method in
 * the same fixture still reports, so a sniff that had fallen silent on the
 * whole file fails this test rather than passing it.
 */
it('stays silent inside a property hook while still reporting beside it', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'property-hooks.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            28 => [SECTION_COMMENT_WARNING],
        ]);
});

/**
 * Pins the detection-only decision: extracting a labelled block into a
 * well-named method is a redesign, not a mechanical rewrite, so no violation
 * is auto-fixable. Warnings rather than errors, because a comment can explain
 * *why* instead of labelling *what* and the token stream cannot tell the two
 * apart — a violation must not fail a consumer's build.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(SECTION_COMMENT, 'failing.php');

    expect($file->getWarningCount())->toBe(13)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * This package's own source is written to the standard, so the rule has to be
 * silent across all of it — every sniff, helper and test file, with the
 * fixtures excluded because they are deliberately non-compliant. The AC calls
 * for this explicitly, and it is the only check that runs the rule over code
 * nobody wrote as a fixture for it.
 *
 * Asserted through the sniff itself rather than a `phpcs` subprocess so the
 * failure names the file and line; tests/Contract/ShippedPackageSmokeTest.php
 * covers the shipped-binary direction.
 */
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
        $warnings = analyzeWithSniffs([SECTION_COMMENT], $path)->getWarnings();

        foreach (array_keys($warnings) as $line) {
            $offenders[] = substr($path, strlen($root) + 1) . ':' . $line;
        }
    }

    expect($offenders)->toBe([]);
});
