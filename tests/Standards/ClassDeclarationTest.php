<?php

declare(strict_types=1);

const CLASS_DECLARATION = 'CleanCode.Classes.ClassDeclaration';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CLASS_DECLARATION);
});

it('replaces its parent rather than running alongside it', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->not->toHaveKey('PSR2.Classes.ClassDeclaration');
});

it('accepts an empty body written as {} on the declaration line of every class-like', function (): void {
    $file = analyzeFixture(CLASS_DECLARATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('keeps every parent check on a body with content, and on the declaration line', function (): void {
    $file = analyzeFixture(CLASS_DECLARATION, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 5, 'column' => 31, 'source' => CLASS_DECLARATION . '.OpenBraceNewLine'],
        ['line' => 9, 'column' => 35, 'source' => CLASS_DECLARATION . '.OpenBraceNewLine'],
        ['line' => 9, 'column' => 65, 'source' => CLASS_DECLARATION . '.CloseBraceAfterBody'],
        ['line' => 11, 'column' => 1, 'source' => CLASS_DECLARATION . '.SpaceAfterKeyword'],
        ['line' => 11, 'column' => 8, 'source' => CLASS_DECLARATION . '.SpaceAfterName'],
        ['line' => 11, 'column' => 17, 'source' => CLASS_DECLARATION . '.SpaceBeforeExtends'],
        ['line' => 11, 'column' => 26, 'source' => CLASS_DECLARATION . '.SpaceBeforeName'],
        ['line' => 13, 'column' => 30, 'source' => CLASS_DECLARATION . '.SpaceBeforeEmptyBody'],
        ['line' => 15, 'column' => 32, 'source' => CLASS_DECLARATION . '.SpaceBeforeEmptyBody'],
        ['line' => 18, 'column' => 1, 'source' => CLASS_DECLARATION . '.OpenBraceNotAlone'],
        ['line' => 20, 'column' => 35, 'source' => CLASS_DECLARATION . '.CloseBraceSameLine'],
        ['line' => 22, 'column' => 7, 'source' => CLASS_DECLARATION . '.SpaceAfterName'],
    ]);
});

it('auto-fixes the failing fixture to exactly the recorded output', function (): void {
    $file = analyzeFixture(CLASS_DECLARATION, 'failing.php');

    expect(autofixedContents($file))->toBe(
            file_get_contents(fixturePath('ClassDeclarationSniff', 'autofixed.php'))
        );
});
