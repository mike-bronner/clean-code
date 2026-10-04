<?php

declare(strict_types=1);

const COUNT_IN_LOOP_SNIFF = 'CleanCode.ControlStructures.DisallowCountInLoopExpression';

const COUNT_IN_LOOP_VIOLATIONS = [
    [4, 19],
    [9, 19],
    [14, 8],
    [18, 8],
    [26, 10],
    [29, 8],
    [33, 31],
    [38, 8],
    [38, 28],
    [44, 19],
    [45, 23],
    [51, 8],
    [56, 19],
    [61, 19],
    [65, 8],
    [70, 15],
    [74, 60],
    [81, 55],
    [89, 9],
    [96, 8],
    [105, 8],
];

const COUNT_IN_LOOP_NESTED_VIOLATIONS = [
    [14, 14],
    [26, 14],
    [37, 23],
    [48, 12],
    [61, 14],
    [71, 36],
    [88, 27],
    [101, 12],
    [113, 27],
    [128, 23],
    [129, 14],
    [143, 15],
    [153, 32],
    [172, 9],
];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COUNT_IN_LOOP_SNIFF);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every count and sizeof call in a loop condition at its exact position', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => COUNT_IN_LOOP_SNIFF . '.Found',
            ],
            COUNT_IN_LOOP_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('names the offending function in the message as it was written', function (): void {
    $errors = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors();

    $messageAt = static function (int $line, int $column) use ($errors): string {
        return $errors[$line][$column][0]['message'];
    };

    expect($messageAt(4, 19))->toStartWith('count() must not be called in a loop condition')
        ->and($messageAt(9, 19))->toStartWith('sizeof() must not be called in a loop condition')
        ->and($messageAt(56, 19))->toStartWith('COUNT() must not be called in a loop condition');
});

it('reports without offering an auto-fix', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php');

    expect($file->getErrorCount())->toBe(count(COUNT_IN_LOOP_VIOLATIONS))
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(COUNT_IN_LOOP_VIOLATIONS), false));
});

it('ignores count in a for loop initialiser or increment', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'for-sections.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('does not mistake a semicolon nested in the for initialiser for a section separator', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'nested-separators.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still finds the condition when an arrow function precedes it in the for header', function (): void {
    $sources = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
        );

    expect($sources)->toHaveKey(81)
        ->and($sources[81])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

it('reports a nested scope in a loop condition once, never twice', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'nested-loops-in-condition.php');

    $expected = array_map(
            static fn (array $position): array => [
                'line' => $position[0],
                'column' => $position[1],
                'source' => COUNT_IN_LOOP_SNIFF . '.Found',
            ],
            COUNT_IN_LOOP_NESTED_VIOLATIONS
        );

    expect(violationTuples($file))->toBe($expected)
        ->and($file->getWarnings())->toBe([]);
});

it('leaves same-named methods, static calls, and qualified names alone', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'same-named-callables.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('ignores the first-class callable syntax, which never invokes the function', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'first-class-callable.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('still flags a real call that spreads its arguments', function (): void {
    $sources = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
        );

    expect($sources)->toHaveKey(96)
        ->and($sources[96])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

it('flags the case and namespace spellings PHPMD misses', function (): void {
    $sources = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
        );

    expect($sources[56])->toBe([COUNT_IN_LOOP_SNIFF . '.Found'])
        ->and($sources[61])->toBe([COUNT_IN_LOOP_SNIFF . '.Found'])
        ->and($sources[65])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);
});

it('refuses a malformed loop header rather than guessing at it', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'unclosed-condition.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('resolves a namespace-relative name against the namespace in force', function (): void {
    $global = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'failing.php')->getErrors()
        );

    expect($global[105])->toBe([COUNT_IN_LOOP_SNIFF . '.Found']);

    $namespaced = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'namespaced-relative.php')->getErrors()
        );

    expect($namespaced)->toBe([22 => [COUNT_IN_LOOP_SNIFF . '.Found']]);
});

it('honours a use-function import that redirects the bare name', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'same-named-callables.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('reads a by-reference declaration as a declaration, not a call', function (): void {
    $file = analyzeFixture(COUNT_IN_LOOP_SNIFF, 'same-named-callables.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('resolves a namespace-relative name against the enclosing block, unnamed included', function (): void {
    $sources = violationSourcesByLine(
            analyzeFixture(COUNT_IN_LOOP_SNIFF, 'namespace-blocks.php')->getErrors()
        );

    expect($sources)->toBe([
        17 => [COUNT_IN_LOOP_SNIFF . '.Found'],
        32 => [COUNT_IN_LOOP_SNIFF . '.Found'],
    ]);
});
