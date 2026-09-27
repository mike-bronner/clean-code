<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const DISALLOW_ELSE = 'CleanCode.Conditionals.DisallowElse';

const DISALLOW_ELSE_FOUND = DISALLOW_ELSE . '.Found';

const DISALLOW_ELSE_IF_FOUND = DISALLOW_ELSE . '.ElseIfFound';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_ELSE);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every else and elseif at its own line and column', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 15, 'column' => 3, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 25, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 36, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 45, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 47, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 58, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 60, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 72, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 75, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 86, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 96, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 108, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 124, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 135, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 146, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 148, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 160, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 175, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 187, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 202, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 220, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 244, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 246, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 257, 'column' => 35, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 266, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 275, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 287, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 297, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 308, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 315, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 326, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 334, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 345, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 357, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 377, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 396, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 407, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
    ]);
});

it('offers a fixer only for the shapes it can rewrite safely', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationFixableFlags($file))->toBe([
        false, false, true, false, false, false, false, false, false, false,
        false, true, true, true, true, true, true, true, true, true,
        true, false, false, false, false, false, false, false, false, false,
        false, false, false, false, false, true, true,
    ]);
});

it('raises no warnings alongside the errors', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect($file->getWarnings())->toBe([]);
});

it('preserves runtime behaviour through the fixer', function (bool $flag, bool $other, array $items): void {
    $original = require fixturePath(sniffFixtureDirectory(DISALLOW_ELSE), 'behaviour.php');

    $path = sys_get_temp_dir() . '/disallow-else-' . uniqid() . '.php';
    file_put_contents($path, autofixedContents(analyzeFixture(DISALLOW_ELSE, 'behaviour.php')));

    try {
        $rewritten = require $path;
    } finally {
        unlink($path);
    }

    expect($rewritten($flag, $other, $items))->toBe($original($flag, $other, $items));
})->with([
    [true, true, []],
    [true, false, [1, 2]],
    [false, true, [1, null, 2]],
    [false, false, [1, false, 2]],
    [false, false, [null, false, null]],
]);

it('rewrites the behaviour fixture into its committed fixed sibling', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'behaviour.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory(DISALLOW_ELSE), 'behaviour.fixed.php')));
});

it('keeps a lifted line as written when its indentation cannot be read', function (): void {
    [$fixed, $diagnostics] = withPhpDiagnostics(static function (): string {
        return PregFailure::during(
            'preg_replace',
            static fn (): string => autofixedContents(analyzeFixture(DISALLOW_ELSE, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/^    /'
        );
    });

    expect($fixed)->toContain("\n            return 2;")
        ->and($fixed)->not->toContain("\nreturn 2;")
        ->and($diagnostics)->toBe([]);
});
