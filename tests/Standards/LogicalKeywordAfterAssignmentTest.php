<?php

declare(strict_types=1);

use PHP_CodeSniffer\Util\Tokens;

const LOGICAL_KEYWORD_AFTER_ASSIGNMENT = 'CleanCode.Operators.LogicalKeywordAfterAssignment';

$family = static function (array $codes): array {
    $names = array_map(
            static fn (int|string $code): string => is_int($code) === true
                ? (string) token_name($code)
                : (string) preg_replace('/^PHPCS_/', '', $code),
            array_values($codes)
        );

    sort($names);

    return $names;
};

$accounted = static function (array $constants): array {
    $all = [];

    foreach ($constants as $constant) {
        $names = tokenNamesInConstant(
                cleanCodeRoot() . '/CleanCode/Sniffs/Operators/LogicalKeywordAfterAssignmentSniff.php',
                $constant,
                [LOGICAL_KEYWORD_AFTER_ASSIGNMENT]
            );

        expect($names)->not->toBeEmpty($constant . ' was found and read');

        $all = array_merge($all, $names);
    }

    sort($all);

    return $all;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(LOGICAL_KEYWORD_AFTER_ASSIGNMENT);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every top-level keyword of an assignment statement at the keyword itself', function (): void {
    $file = analyzeFixture(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, 'failing.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => LOGICAL_KEYWORD_AFTER_ASSIGNMENT . '.Found',
            ],
            [
                [3, 21],
                [4, 9],
                [4, 15],
                [6, 9],
                [7, 10],
                [8, 10],
                [9, 10],
                [10, 10],
                [11, 10],
                [12, 11],
                [13, 10],
                [14, 11],
                [15, 10],
                [16, 10],
                [17, 10],
                [18, 11],
                [19, 11],
                [20, 10],
                [22, 14],
                [23, 11],
                [24, 15],
                [25, 17],
                [26, 14],
                [27, 17],
                [28, 19],
                [29, 15],
                [30, 29],
                [31, 21],
                [32, 14],
                [34, 29],
                [35, 29],
                [36, 29],
                [38, 9],
                [39, 9],
                [40, 9],
                [41, 9],
                [45, 13],
                [49, 13],
                [53, 13],
                [56, 17],
                [59, 13],
                [64, 17],
                [67, 17],
                [71, 5],
                [75, 5],
                [81, 13],
                [85, 13],
                [90, 15],
                [93, 34],
                [94, 21],
                [95, 19],
                [96, 18],
                [99, 14],
                [102, 21],
                [104, 28],
                [105, 20],
                [106, 18],
                [107, 12],
                [108, 26],
                [111, 13],
                [115, 13],
                [121, 13],
                [124, 10],
                [125, 13],
                [126, 15],
                [127, 17],
                [128, 18],
                [129, 19],
                [130, 24],
                [132, 27],
                [133, 37],
                [134, 34],
                [135, 29],
                [137, 16],
                [142, 13],
                [145, 11],
                [146, 16],
            ]
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('stops at an unmatched closer without reporting', function (string $fixture): void {
    $file = analyzeFixture(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with([
    'parenthesis' => ['stray-parenthesis.php'],
    'square bracket' => ['stray-bracket.php'],
]);

it('names the keyword in lower case and suggests its replacement', function (
    int $line,
    int $column,
    string $message
): void {
    $errors = analyzeFixture(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, 'failing.php')->getErrors();

    expect($errors[$line][$column][0]['message'])->toBe($message);
})->with([
    'or' => [3, 21, 'The or keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Use || instead, or wrap the right-hand side in parentheses.'],
    'and' => [4, 15, 'The and keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Use && instead, or wrap the right-hand side in parentheses.'],
    'xor' => [41, 9, 'The xor keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Wrap the right-hand side in parentheses.'],
    'OR' => [38, 9, 'The or keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Use || instead, or wrap the right-hand side in parentheses.'],
    'And' => [39, 9, 'The and keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Use && instead, or wrap the right-hand side in parentheses.'],
    'XOR' => [40, 9, 'The xor keyword binds looser than the assignment before it, so only its left operand is assigned.'
        . ' Wrap the right-hand side in parentheses.'],
]);

it('has no fixer, so phpcbf leaves the failing fixture byte-identical', function (): void {
    $file = analyzeFixture(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, 'failing.php');
    $original = (string) file_get_contents(fixturePath('LogicalKeywordAfterAssignmentSniff', 'failing.php'));

    expect($file->getFixableCount())->toBe(0)
        ->and(autofixedContents($file))->toBe($original)
        ->and(in_array(LOGICAL_KEYWORD_AFTER_ASSIGNMENT, AUTOFIXABLE_SNIFFS, true))->toBeFalse();
});

it('accounts for every boolean operator PHPCS defines', function () use ($family, $accounted): void {
    expect($accounted(['LOGICAL_KEYWORDS', 'SYMBOL_OPERATORS']))
        ->toBe($family(Tokens::$booleanOperators));
});

it('accounts for every assignment token PHPCS defines', function () use ($family, $accounted): void {
    expect($accounted(['ASSIGNMENT_OPERATORS', 'NON_ASSIGNMENT_OPERATORS']))
        ->toBe($family(Tokens::$assignmentTokens));
});

it('accounts for every scope opener PHPCS defines', function () use ($family, $accounted): void {
    expect($accounted(['EXPRESSION_SCOPES', 'BRACELESS_BODY_SCOPES', 'BLOCK_SCOPES']))
        ->toBe($family(Tokens::$scopeOpeners));
});
