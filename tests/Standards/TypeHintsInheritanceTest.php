<?php

declare(strict_types=1);

const CLEANCODE_PARAMETER_TYPE_HINT = 'CleanCode.TypeHints.ParameterTypeHint';

const CLEANCODE_PROPERTY_TYPE_HINT = 'CleanCode.TypeHints.PropertyTypeHint';

it('skips a parameter an ancestor declares untyped, and reports every other', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([11, 34, 44]);
});

it('stays silent on the declaration whose ancestor leaves the parameter untyped', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->not->toHaveKey(26, 'process() overrides an untyped Sniff::process()');
});

it('still reports a sibling declaration its loadable ancestor does not declare', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(34);
});

it('still reports a declaration whose ancestor cannot be resolved', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(44);
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
