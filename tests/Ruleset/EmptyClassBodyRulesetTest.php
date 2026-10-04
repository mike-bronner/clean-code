<?php

declare(strict_types=1);

const EMPTY_BODY_CLASS_DECLARATION = 'CleanCode.Classes.ClassDeclaration';
const EMPTY_BODY_SCOPE_CLOSING_BRACE = 'CleanCode.WhiteSpace.ScopeClosingBrace';

$analyzeEmptyBody = static fn (string $fixture): array => allViolationSourcesByLine(
        analyzeWithMasterRuleset(fixturePath('_rulesets/EmptyClassBody', $fixture))
    );

it('swaps both brace sniffs for their CleanCode replacements', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EMPTY_BODY_CLASS_DECLARATION)
        ->and($ruleset->sniffCodes)->toHaveKey(EMPTY_BODY_SCOPE_CLOSING_BRACE)
        ->and($ruleset->sniffCodes)->not->toHaveKey('PSR2.Classes.ClassDeclaration')
        ->and($ruleset->sniffCodes)->not->toHaveKey('Squiz.WhiteSpace.ScopeClosingBrace');
});

it('accepts an empty class-like written as {} on its declaration line', function (string $fixture) use (
    $analyzeEmptyBody
): void {
    expect($analyzeEmptyBody($fixture))->toBe([]);
})->with([
    'model' => ['model.php'],
    'interface' => ['interface.php'],
    'trait' => ['trait.php'],
    'enum' => ['enum.php'],
]);

it('still reports both braces of a body that holds only a comment', function () use ($analyzeEmptyBody): void {
    expect($analyzeEmptyBody('comment-body.php'))->toBe([
        7 => [
            EMPTY_BODY_CLASS_DECLARATION . '.CloseBraceAfterBody',
            EMPTY_BODY_CLASS_DECLARATION . '.OpenBraceNewLine',
            EMPTY_BODY_SCOPE_CLOSING_BRACE . '.ContentBefore',
        ],
    ]);
});

it('still reports both braces of a body with code in it', function () use ($analyzeEmptyBody): void {
    expect($analyzeEmptyBody('content-body.php'))->toBe([
        7 => [
            EMPTY_BODY_CLASS_DECLARATION . '.CloseBraceAfterBody',
            EMPTY_BODY_CLASS_DECLARATION . '.OpenBraceNewLine',
            EMPTY_BODY_SCOPE_CLOSING_BRACE . '.ContentBefore',
            'PSR12.Traits.UseDeclaration.UseAfterBrace',
        ],
    ]);
});

it('still reports declaration-line spacing on an empty class-like', function () use ($analyzeEmptyBody): void {
    expect($analyzeEmptyBody('declaration-spacing.php'))->toBe([
        7 => [
            EMPTY_BODY_CLASS_DECLARATION . '.SpaceAfterKeyword',
            EMPTY_BODY_CLASS_DECLARATION . '.SpaceAfterName',
            EMPTY_BODY_CLASS_DECLARATION . '.SpaceBeforeExtends',
            EMPTY_BODY_CLASS_DECLARATION . '.SpaceBeforeName',
        ],
    ]);
});
