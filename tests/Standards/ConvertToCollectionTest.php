<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

const CONVERT_TO_COLLECTION = 'CleanCode.Arrays.ConvertToCollection';

const CONVERT_TO_COLLECTION_TUPLES = [
    ['line' => 4, 'column' => 10],
    ['line' => 5, 'column' => 11],
    ['line' => 6, 'column' => 10],
    ['line' => 10, 'column' => 10],
    ['line' => 14, 'column' => 12],
    ['line' => 19, 'column' => 12],
    ['line' => 27, 'column' => 16],
    ['line' => 33, 'column' => 11],
    ['line' => 37, 'column' => 12],
    ['line' => 37, 'column' => 30],
    ['line' => 44, 'column' => 13],
];

const CONVERT_TO_COLLECTION_MESSAGES = [
    'array_map() manipulates a native array; use collect()->map() instead',
    'array_filter() manipulates a native array; use collect()->filter() instead',
    'array_reduce() manipulates a native array; use collect()->reduce() instead',
    'ARRAY_MAP() manipulates a native array; use collect()->map() instead',
    'array_map() manipulates a native array; use collect()->map() instead',
    'array_filter() manipulates a native array; use collect()->filter() instead',
    'array_reduce() manipulates a native array; use collect()->reduce() instead',
    'array_map() manipulates a native array; use collect()->map() instead',
    'array_map() manipulates a native array; use collect()->map() instead',
    'array_filter() manipulates a native array; use collect()->filter() instead',
    'array_filter() manipulates a native array; use collect()->filter() instead',
];

$convertToCollectionMessages = static function (LocalFile $file): array {
    $messages = [];

    foreach ($file->getWarnings() as $line => $columns) {
        foreach ($columns as $column => $lineMessages) {
            foreach ($lineMessages as $index => $message) {
                $messages[sprintf('%09d.%09d.%03d', $line, $column, $index)] = $message['message'];
            }
        }
    }

    ksort($messages);

    return array_values($messages);
};

$analyzeWithArrayFunctions = static function (string $fixture, array $arrayFunctions): LocalFile {
    return analyzeFixture(
            CONVERT_TO_COLLECTION,
            $fixture,
            static function (object $sniff) use ($arrayFunctions): void {
                $sniff->arrayFunctions = $arrayFunctions;
            }
        );
};

$analyzeThroughRulesetFile = static function (string $ruleset, string $fixture): LocalFile {
    $directory = sniffFixtureDirectory(CONVERT_TO_COLLECTION);

    $config = new ConfigDouble(['--standard=' . fixturePath($directory, $ruleset)]);
    $config->cache = false;

    $file = new LocalFile(fixturePath($directory, $fixture), new Ruleset($config), $config);
    $file->process();

    return $file;
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONVERT_TO_COLLECTION);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns on every native array call at its own line and column', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    $expected = [];

    foreach (CONVERT_TO_COLLECTION_TUPLES as $tuple) {
        $expected[] = $tuple + ['source' => CONVERT_TO_COLLECTION . '.Found'];
    }

    expect(warningTuples($file))->toBe($expected);
});

it('names the Collection equivalent of each flagged function', function () use ($convertToCollectionMessages): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    expect($convertToCollectionMessages($file))->toBe(CONVERT_TO_COLLECTION_MESSAGES);
});

it('reports warnings rather than errors', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    expect($file->getWarningCount())->toBe(count(CONVERT_TO_COLLECTION_TUPLES))
        ->and($file->getErrors())->toBe([])
        ->and($file->getErrorCount())->toBe(0);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    expect($file->getFixableCount())->toBe(0);
});

it('flags nothing once the shipped functions leave the configured list', function () use (
    $analyzeWithArrayFunctions
): void {
    $file = $analyzeWithArrayFunctions('failing.php', ['array_walk' => 'each']);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
});

it('flags a configured function and names its configured equivalent', function () use (
    $analyzeWithArrayFunctions,
    $convertToCollectionMessages
): void {
    $file = $analyzeWithArrayFunctions('passing.php', ['ARRAY_WALK' => 'each']);

    expect(warningTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_walk() manipulates a native array; use collect()->each() instead',
    ]);
});

it('takes both element-node property spellings from a real ruleset file', function () use (
    $analyzeThroughRulesetFile,
    $convertToCollectionMessages
): void {
    $file = $analyzeThroughRulesetFile('element-property.xml', 'passing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 12, 'column' => 11, 'source' => CONVERT_TO_COLLECTION . '.Found'],
        ['line' => 15, 'column' => 1, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_values() manipulates a native array; use collect()->values() instead',
        'usort() manipulates a native array; use collect()->sortBy() instead',
    ]);
});

it('stays silent on a namespace-relative call inside a declared namespace', function () use (
    $convertToCollectionMessages
): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'namespaced.php');

    expect(warningTuples($file))->toBe([
        ['line' => 19, 'column' => 10, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_map() manipulates a native array; use collect()->map() instead',
    ]);
});

it('flags a namespace-relative call inside a global namespace block', function () use (
    $convertToCollectionMessages
): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'namespaced-blocks.php');

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 14, 'source' => CONVERT_TO_COLLECTION . '.Found'],
        ['line' => 22, 'column' => 15, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_map() manipulates a native array; use collect()->map() instead',
        'array_filter() manipulates a native array; use collect()->filter() instead',
    ]);
});
