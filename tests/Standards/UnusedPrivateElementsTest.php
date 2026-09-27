<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const UNUSED_PRIVATE_ELEMENTS = 'CleanCode.DeadCode.UnusedPrivateElements';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_PRIVATE_ELEMENTS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every dead private member and nothing else', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 22, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 41, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 53, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 78, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 93, 'column' => 28, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 100, 'column' => 30, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
        ['line' => 117, 'column' => 20, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty'],
        ['line' => 136, 'column' => 22, 'source' => UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod'],
    ]);
});

it('reaches dead members in the class-like constructs it registers', function (int $line, string $code): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey($line)
        ->and($lines[$line])->toBe(UNUSED_PRIVATE_ELEMENTS . '.' . $code);
})->with([
    'named class property' => [13, 'UnusedProperty'],
    'named class method' => [22, 'UnusedMethod'],
    'enum method' => [78, 'UnusedMethod'],
    'anonymous class property' => [93, 'UnusedProperty'],
    'anonymous class method' => [100, 'UnusedMethod'],
]);

it('leaves a trait body alone even when nothing in it uses its private members', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect(violationTuples($file))->toBe([])
        ->and(file_get_contents(fixturePath('UnusedPrivateElementsSniff', 'passing.php')))
        ->toContain('private function secretHelper');
});

it('does not let a nested anonymous class excuse a same-named host member', function (int $line, string $code): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey($line)
        ->and($lines[$line])->toBe(UNUSED_PRIVATE_ELEMENTS . '.' . $code);
})->with([
    'host property read only inside the nested body' => [117, 'UnusedProperty'],
    'host method called only inside the nested body' => [136, 'UnusedMethod'],
]);

it('still counts a usage in the arguments of a nested anonymous class', function (): void {
    $file = analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'passing.php');

    expect(violationTuples($file))->toBe([])
        ->and(file_get_contents(fixturePath('UnusedPrivateElementsSniff', 'passing.php')))
        ->toContain('new class ($this->config)');
});

it('does not let a property read excuse a same-named method, or the reverse', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')), 'source', 'line');

    expect($lines)->toHaveKey(41)
        ->and($lines[41])->toBe(UNUSED_PRIVATE_ELEMENTS . '.UnusedMethod')
        ->and($lines)->toHaveKey(53)
        ->and($lines[53])->toBe(UNUSED_PRIVATE_ELEMENTS . '.UnusedProperty');
});

it('skips a string literal whose words cannot be read', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_match_all',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(UNUSED_PRIVATE_ELEMENTS, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
