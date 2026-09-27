<?php

declare(strict_types=1);

const REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT = 'CleanCode.Strings.RequireHeredocForStructuredText';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every violation at its own line and column', function (): void {
    $file = analyzeFixture(REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 11, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 4, 'column' => 15, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 5, 'column' => 16, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 6, 'column' => 10, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 7, 'column' => 16, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 11, 'column' => 10, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 12, 'column' => 9, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 13, 'column' => 8, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 14, 'column' => 13, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
        ['line' => 15, 'column' => 8, 'source' => REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString'],
    ]);
});

it('offers no fix for any violation', function (): void {
    $file = analyzeFixture(REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT, 'failing.php');

    expect($file->getErrorCount())->toBe(10)
        ->and($file->getFixableCount())->toBe(0);
});

it('stays silent on tag-shaped text that is not markup', function (string $source): void {
    $file = analyzeStdinSource([REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT], "<?php\n\n\$x = {$source};\n");

    expect($file->getErrors())->toBe([]);
})->with([
    "'a < b and c > d'",
    "'List<int>'",
    '"run script.sh <input.txt >output.txt"',
    "'see <time.h> for details'",
    "'a <div without a close'",
]);

it('still flags a real element in that position', function (): void {
    $file = analyzeStdinSource([REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT], "<?php\n\n\$x = '<div>real</div>';\n");

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([3 => [REQUIRE_HEREDOC_FOR_STRUCTURED_TEXT . '.StructuredTextInString']]);
});
