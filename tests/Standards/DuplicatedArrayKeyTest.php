<?php

declare(strict_types=1);

const DUPLICATED_ARRAY_KEY = 'CleanCode.Arrays.DuplicatedArrayKey';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DUPLICATED_ARRAY_KEY);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags every duplicate key at the overriding entry', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php');

    expect(violationTuples($file))->toBe(array_map(
        static fn (array $position): array => [
            'line' => $position[0],
            'column' => $position[1],
            'source' => DUPLICATED_ARRAY_KEY . '.Found',
        ],
        [
            [7, 5], [9, 5], [16, 5], [17, 5], [23, 5], [29, 5], [30, 5], [31, 5],
            [32, 5], [38, 5], [40, 5], [47, 5], [54, 5], [55, 5], [59, 25],
            [65, 9], [67, 5], [72, 17], [79, 5], [81, 5], [91, 5], [93, 5],
            [102, 5], [110, 5], [118, 5], [125, 5], [135, 5], [136, 5],
            [144, 5], [151, 5], [152, 5], [153, 5], [154, 5],
        ]
    ))->and($file->getWarnings())->toBe([]);
});

it('names the coerced key and the declaration it overrides', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[7])->toBe(['Duplicate array key 0 overrides the entry on line 6; remove one of them'])
        ->and($messages[9])->toBe(["Duplicate array key 'foo' overrides the entry on line 8; remove one of them"])
        ->and($messages[16])->toBe(['Duplicate array key 1 overrides the entry on line 15; remove one of them'])
        ->and($messages[17])->toBe(['Duplicate array key 1 overrides the entry on line 15; remove one of them'])
        ->and($messages[23])->toBe(["Duplicate array key '' overrides the entry on line 22; remove one of them"])
        ->and($messages[30])->toBe(['Duplicate array key 15 overrides the entry on line 28; remove one of them']);
});

it('reports every repeat against the first declaration', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[54])->toBe(["Duplicate array key 'k' overrides the entry on line 53; remove one of them"])
        ->and($messages[55])->toBe(["Duplicate array key 'k' overrides the entry on line 53; remove one of them"]);
});

it('resolves a float key outside the integer range the way PHP does', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[91])
        ->toBe(['Duplicate array key 5076964154930102272 overrides the entry on line 89; remove one of them'])
        ->and($messages[93])
        ->toBe(['Duplicate array key -5076964154930102272 overrides the entry on line 92; remove one of them'])
        ->and($messages)->not->toHaveKey(90)
        ->and($messages[47])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 46; remove one of them'])
        ->and($messages[102])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 101; remove one of them'])
        ->and($messages[110])
        ->toBe(['Duplicate array key 0 overrides the entry on line 109; remove one of them'])
        ->and($messages[118])
        ->toBe(['Duplicate array key -9223372036854775808 overrides the entry on line 117; remove one of them'])
        ->and($messages[125])
        ->toBe(['Duplicate array key 8292815763849347072 overrides the entry on line 124; remove one of them']);
});

it('resolves such a key without aborting the installed run', function (): void {
    $run = installedSniffFixtureRun(DUPLICATED_ARRAY_KEY, 'failing.php');
    $sources = array_column($run['messages'], 'source');

    expect($sources)->not->toContain('Internal.Exception')
        ->and(array_unique($sources))->toBe([DUPLICATED_ARRAY_KEY . '.Found'])
        ->and(array_column($run['messages'], 'line'))->toContain(47, 91, 93, 102, 110, 118, 125);
});

it('reads a leading zero as octal only when the digits are octal', function (): void {
    $messages = violationMessagesByLine(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')->getErrors());

    expect($messages[135])->toBe(['Duplicate array key 0 overrides the entry on line 134; remove one of them'])
        ->and($messages[136])->toBe(['Duplicate array key 0 overrides the entry on line 134; remove one of them'])
        ->and($messages[144])->toBe(['Duplicate array key 5 overrides the entry on line 143; remove one of them']);
});

it('declines a malformed literal without aborting the installed run', function (): void {
    $run = installedSniffFixtureRun(DUPLICATED_ARRAY_KEY, 'passing.php');

    expect(array_column($run['messages'], 'source'))->not->toContain('Internal.Exception')
        ->and($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->not->toContain(true);
});

it('leaves the failing fixture byte-identical under the fixer', function (): void {
    $path = fixturePath(sniffFixtureDirectory(DUPLICATED_ARRAY_KEY), 'failing.php');

    expect(autofixedContents(analyzeFixture(DUPLICATED_ARRAY_KEY, 'failing.php')))
        ->toBe(file_get_contents($path));
});

it('pins where the sniff and PHPMD differ', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'divergences.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        8 => [DUPLICATED_ARRAY_KEY . '.Found'],
        10 => [DUPLICATED_ARRAY_KEY . '.Found'],
        11 => [DUPLICATED_ARRAY_KEY . '.Found'],
        18 => [DUPLICATED_ARRAY_KEY . '.Found'],
        34 => [DUPLICATED_ARRAY_KEY . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

it('stays silent on an unterminated array', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'unterminated-array.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('stays silent on an unterminated construct inside an array', function (): void {
    $file = analyzeFixture(DUPLICATED_ARRAY_KEY, 'unterminated-index.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('leaves the package own test source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/tests/Standards/*.php');

    expect($paths)->toBeArray()
        ->and(count($paths))->toBeGreaterThanOrEqual(70)
        ->and($paths)->toContain(
            cleanCodeRoot() . '/tests/Standards/ConvertToCollectionTest.php',
            cleanCodeRoot() . '/tests/Standards/DuplicatedArrayKeyTest.php',
        );

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([DUPLICATED_ARRAY_KEY], $path);
        $relative = basename($path);

        expect($file->numTokens)->toBeGreaterThan(0, "{$relative} produced no tokens")
            ->and(array_column(violationTuples($file), 'source'))
            ->not->toContain(DUPLICATED_ARRAY_KEY . '.Found');
    }
});

it('reads no tokens from a source the sweep would otherwise call clean', function (): void {
    $empty = analyzeWithSniffs([DUPLICATED_ARRAY_KEY], stageGeneratedFixture('empty-source.php', ''));
    $missing = analyzeWithSniffs([DUPLICATED_ARRAY_KEY], cleanCodeRoot() . '/tests/Standards/no-such-file.php');

    expect($empty->numTokens)->toBe(0)
        ->and($empty->getErrors())->toBe([])
        ->and($missing->numTokens)->toBe(0)
        ->and(violationSourcesByLine($missing->getErrors()))->toBe([1 => ['Internal.LocalFile']])
        ->and(array_column(violationTuples($missing), 'source'))
        ->not->toContain(DUPLICATED_ARRAY_KEY . '.Found');
});
