<?php

declare(strict_types=1);

const SCOPE_CLOSING_BRACE = 'CleanCode.WhiteSpace.ScopeClosingBrace';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SCOPE_CLOSING_BRACE);
});

it('replaces its parent rather than running alongside it', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey('Squiz.WhiteSpace.ScopeClosingBrace');
});

it('accepts an empty body written as {} on the declaration line of every class-like', function (): void {
    $file = analyzeFixture(SCOPE_CLOSING_BRACE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('keeps every parent check on a body with content, a comment included', function (): void {
    $file = analyzeFixture(SCOPE_CLOSING_BRACE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 5, 'column' => 46, 'source' => SCOPE_CLOSING_BRACE . '.ContentBefore'],
        ['line' => 7, 'column' => 65, 'source' => SCOPE_CLOSING_BRACE . '.ContentBefore'],
        ['line' => 9, 'column' => 57, 'source' => SCOPE_CLOSING_BRACE . '.ContentBefore'],
        ['line' => 15, 'column' => 27, 'source' => SCOPE_CLOSING_BRACE . '.ContentBefore'],
        ['line' => 23, 'column' => 7, 'source' => SCOPE_CLOSING_BRACE . '.Indent'],
    ]);
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeFixture(SCOPE_CLOSING_BRACE, 'failing.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(fixturePath('ScopeClosingBraceSniff', 'autofixed.php'))
    );
});
