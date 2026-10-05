<?php

declare(strict_types=1);

const CLEANCODE_PARAMETER_TYPE_HINT = 'CleanCode.TypeHints.ParameterTypeHint';

const CLEANCODE_PROPERTY_TYPE_HINT = 'CleanCode.TypeHints.PropertyTypeHint';

it('skips a parameter an ancestor declares untyped, and reports every other', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([8, 31, 41]);
});

it('stays silent on the declaration whose ancestor leaves the parameter untyped', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->not->toHaveKey(23, 'filter() overrides an untyped php_user_filter::filter()');
});

it('still reports a sibling declaration its loadable ancestor does not declare', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(31);
});

it('still reports a declaration whose ancestor cannot be resolved', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(41);
});

it('skips a PHP_CodeSniffer class property, and reports an ordinary one', function (): void {
    $file = analyzeFixture(CLEANCODE_PROPERTY_TYPE_HINT, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([11]);
});

const INFERRED_RETURN_TYPE = 'CleanCode.TypeHints.InferredReturnType';

it('reports only the return types it can prove', function (): void {
    $file = analyzeFixture(INFERRED_RETURN_TYPE, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([40, 50, 60, 66, 72, 95, 97, 110])
        ->and($file->getWarnings())->toBe([]);
});

it('writes each proven type onto its signature', function (): void {
    $fixed = autofixedContents(analyzeFixture(INFERRED_RETURN_TYPE, 'failing.php'));

    expect($fixed)
        ->toContain('public function bothBooleans(int $flag): bool')
        ->toContain('public function stringOrNull(int $flag): ?string')
        ->toContain('public function returnsThis(): static')
        ->toContain('public function typedParameter(File $phpcsFile): File')
        ->toContain('public function valueOrBareReturn(int $flag): ?int')
        ->toContain('public function closureReturnIsNotMine(): true')
        ->toContain('public static function getName($phpcsFile, $classPointer): string')
        ->toContain('public function noReturnAtAll()' . "\n")
        ->toContain('public function bareReturnOnly(int $flag)' . "\n")
        ->toContain('public function __construct(private int $flag = 0)' . "\n")
        ->toContain('public function unprovableCall(File $phpcsFile, int $ptr)' . "\n")
        ->toContain('public function unprovableExpression(int $flag)' . "\n");
});

it('skips a property whose nearest ancestor declaration is untyped, with no docblock', function (): void {
    $file = analyzeFixture(CLEANCODE_PROPERTY_TYPE_HINT, 'inherited-untyped.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still reports a property no untyped, non-private ancestor declares, and never crashes', function (): void {
    $file = analyzeFixture(CLEANCODE_PROPERTY_TYPE_HINT, 'inherited-reported.php');
    $missing = CLEANCODE_PROPERTY_TYPE_HINT . '.MissingAnyTypeHint';

    expect(violationTuples($file))->toBe([
        ['line' => 15, 'column' => 12, 'source' => $missing],
        ['line' => 22, 'column' => 19, 'source' => $missing],
        ['line' => 28, 'column' => 15, 'source' => $missing],
        ['line' => 35, 'column' => 15, 'source' => $missing],
        ['line' => 41, 'column' => 12, 'source' => $missing],
        ['line' => 47, 'column' => 12, 'source' => $missing],
    ])->and($file->getWarnings())->toBe([]);
});

it('reports a use-clause closure only when it declares no return type', function (): void {
    $file = analyzeFixture(INFERRED_RETURN_TYPE, 'use-clause.php');
    $errors = $file->getErrors();
    $warnings = $file->getWarnings();

    expect(array_keys($errors))
        ->toBe([17, 26, 36])
        ->and($warnings)
        ->toBe([]);
});

it('writes the type after the use list, and the result parses', function (): void {
    $fixed = autofixedContents(analyzeFixture(INFERRED_RETURN_TYPE, 'use-clause.php'));
    $expected = file_get_contents(fixturePath('InferredReturnTypeSniff', 'use-clause.fixed.php'));
    $parse = static fn (): array => token_get_all($fixed, TOKEN_PARSE);

    expect($fixed)
        ->toBe($expected)
        ->and($parse)
        ->not
        ->toThrow(ParseError::class);
});

it('leaves an already-fixed use-clause closure alone on a second pass', function (): void {
    $file = analyzeFixture(INFERRED_RETURN_TYPE, 'use-clause.fixed.php');
    $errors = $file->getErrors();
    $warnings = $file->getWarnings();

    expect($errors)
        ->toBe([])
        ->and($warnings)
        ->toBe([]);
});

it('stays silent when the use list has no closing parenthesis to anchor on', function (): void {
    $file = analyzeStdinSource([INFERRED_RETURN_TYPE], <<<PHP
        <?php
        \$handler = function () use {
            return true;
        };
        PHP);
    $errors = $file->getErrors();
    $warnings = $file->getWarnings();
    $fixable = $file->getFixableCount();

    expect($errors)
        ->toBe([])
        ->and($warnings)
        ->toBe([])
        ->and($fixable)
        ->toBe(0);
});
