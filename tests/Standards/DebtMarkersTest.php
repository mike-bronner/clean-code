<?php

/**
 * Tests the custom CleanCode.Commenting.DebtMarkers sniff (Debt: Technical
 * Debt, #24 / #138). Fixtures live in tests/fixtures/DebtMarkersSniff/ and
 * carry the two halves of the contract: passing.php is clean, failing.php
 * holds every violation code in every comment style.
 *
 * The sniff owns only HACK and XXX. TODO and FIXME are PHPCS core's, wired
 * into rules.xml and covered by tests/Ruleset/DebtMarkerCommentsTest.php —
 * which is also where the four keywords are asserted to behave as one rule.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const DEBT_MARKERS = 'CleanCode.Commenting.DebtMarkers';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DEBT_MARKERS);
});

/**
 * The negative case the acceptance criteria ask for, plus the near misses that
 * make it discriminating: "hacked", "shack", "hacksaw", "Hackathon",
 * "$hackathonSlug" and "XXXX" all contain a marker's letters but are not the
 * marker, and TODO and FIXME are markers this sniff does not own.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every marker, at its own line and column, under its own code. The four codes
 * split two ways: the keyword (Hack / Xxx) and whether a task description
 * follows it (TaskFound) or the marker stands alone (CommentFound).
 *
 * The comment styles are pinned by position: lines 3-4 are slash line
 * comments, 5-6 hash line comments, 8 a single-line block comment, 10 the body
 * of a multi-line block comment, 16-17 docblock prose, and 21 an inline
 * comment carrying both markers at once.
 */
it('flags every marker at its own line with the expected code', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => DEBT_MARKERS . '.HackTaskFound'],
        ['line' => 4, 'column' => 1, 'source' => DEBT_MARKERS . '.XxxTaskFound'],
        ['line' => 5, 'column' => 1, 'source' => DEBT_MARKERS . '.HackTaskFound'],
        ['line' => 6, 'column' => 1, 'source' => DEBT_MARKERS . '.XxxCommentFound'],
        ['line' => 8, 'column' => 1, 'source' => DEBT_MARKERS . '.HackTaskFound'],
        ['line' => 10, 'column' => 1, 'source' => DEBT_MARKERS . '.XxxCommentFound'],
        ['line' => 16, 'column' => 4, 'source' => DEBT_MARKERS . '.HackCommentFound'],
        ['line' => 17, 'column' => 4, 'source' => DEBT_MARKERS . '.XxxTaskFound'],
        ['line' => 21, 'column' => 5, 'source' => DEBT_MARKERS . '.HackTaskFound'],
        ['line' => 21, 'column' => 5, 'source' => DEBT_MARKERS . '.XxxCommentFound'],
    ]);
});

/**
 * The acceptance criteria require a report to say which keyword triggered it,
 * so the keyword is in the message text and not only in the code — a consumer
 * reading a summarized report sees HACK or XXX either way.
 */
it('names the triggering keyword in every message', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'failing.php');

    expect(violationMessagesByLine($file->getWarnings()))->toBe([
        3 => ['Comment refers to a HACK task "resolve the container by hand until the binding lands"'],
        4 => ['Comment refers to a XXX task "the retry count is a guess"'],
        5 => ['Comment refers to a HACK task "the flag is read straight off the request"'],
        6 => ['Comment refers to a XXX task'],
        8 => ['Comment refers to a HACK task "reaches for the facade because the service is not injectable yet */"'],
        10 => ['Comment refers to a XXX task'],
        16 => ['Comment refers to a HACK task'],
        17 => ['Comment refers to a XXX task "rounding drops a cent on every third row"'],
        21 => [
            'Comment refers to a HACK task "this branch exists only for the legacy payload. XXX"',
            'Comment refers to a XXX task',
        ],
    ]);
});

/**
 * Warnings, never errors, and nothing fixable. A marker documents debt the
 * developer already recognized; the only mechanical rewrite available is
 * deleting the comment, which would drop the recognition without paying the
 * debt. Asserted directly rather than inferred from the report, because
 * promoting the sniff to an error would leave every assertion above intact.
 */
it('reports warnings only, and offers no fix', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(10)
        ->and($file->getFixableCount())->toBe(0);
});
