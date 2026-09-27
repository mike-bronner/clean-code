<?php

declare(strict_types=1);

const DEBT_MARKERS = 'CleanCode.Commenting.DebtMarkers';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DEBT_MARKERS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

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

it('reports warnings only, and offers no fix', function (): void {
    $file = analyzeFixture(DEBT_MARKERS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(10)
        ->and($file->getFixableCount())->toBe(0);
});
