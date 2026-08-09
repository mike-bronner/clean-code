<?php

/**
 * Tests the custom CleanCode.Arrays.ConvertToCollection sniff, the partial
 * enforcement of Arrays: Convert To Collection (#30, sniff #165) —
 * docs/standards/arrays-convert-to-collection.md.
 *
 * The sniff flags the standard's *trigger* only: a bare call to a configured
 * native array function. Whether the manipulation reads better as a pipeline
 * is a judgement, so the reports are warnings and there is no fixer — the
 * severity and detection-only tests below pin both, because a regression to
 * errors or to a fixable report would still leave every line assertion here
 * passing.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const CONVERT_TO_COLLECTION = 'CleanCode.Arrays.ConvertToCollection';

/**
 * Every warning the failing fixture must produce, as line/column/source. The
 * column matters as much as the line: it is what pins the report onto the
 * function-name token rather than the statement, and it is the only thing
 * separating the two nested calls that share line 37.
 */
const CONVERT_TO_COLLECTION_TUPLES = [
    ['line' => 4, 'column' => 10],
    ['line' => 5, 'column' => 11],
    ['line' => 6, 'column' => 10],
    ['line' => 10, 'column' => 10],
    ['line' => 14, 'column' => 13],
    ['line' => 19, 'column' => 12],
    ['line' => 27, 'column' => 16],
    ['line' => 33, 'column' => 11],
    ['line' => 37, 'column' => 12],
    ['line' => 37, 'column' => 30],
];

/**
 * Every warning message the failing fixture must produce, in report order.
 * Asserted verbatim because the Collection method named in the message is an
 * acceptance criterion in its own right — a sniff that flagged the right
 * tokens while naming the wrong replacement would pass the tuple test above.
 */
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
];

/**
 * Flattens a processed file's warnings to the messages alone, in the same
 * line/column order warningTuples() reports.
 *
 * A closure rather than a named function because this file also calls it() at
 * file scope, and PSR-1 forbids a file that both declares symbols and executes
 * side effects — the same reason tests/Ruleset/UnusedLocalVariableTest.php
 * holds its helper in a variable.
 *
 * @var callable(LocalFile): array<int, string>
 */
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

/**
 * Runs a fixture with the flagged-function list replaced, the way a consuming
 * ruleset would replace it with <property name="arrayFunctions" type="array"
 * value="…"/>.
 *
 * @var callable(string, array<array-key, string>): LocalFile
 */
$analyzeWithArrayFunctions = static function (string $fixture, array $arrayFunctions): LocalFile {
    return analyzeFixture(
        CONVERT_TO_COLLECTION,
        $fixture,
        static function (object $sniff) use ($arrayFunctions): void {
            $sniff->arrayFunctions = $arrayFunctions;
        }
    );
};

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONVERT_TO_COLLECTION);
});

/**
 * passing.php pairs the Collection pipelines the standard asks for with every
 * near-miss shape the sniff must stay silent on — the configured names reached
 * through an object operator, a nullsafe operator and a double colon, declared
 * as a function, a method and a static method, used as a type hint and as a
 * bare constant (the two shapes that reach the sniff without an open
 * parenthesis after them), as a string, a property, a class being
 * instantiated, and behind a namespace prefix, plus four native array
 * functions outside the configured list. Each of those is one of the sniff's
 * guards, so the fixture's silence is a verdict about them rather than merely
 * the absence of an array call.
 *
 * The named argument in the fixture is the one shape that is *not* a verdict:
 * PHPCS tokenizes it as T_PARAM_NAME, so register() never offers it to the
 * sniff. It is kept as a note against adding a guard for an unreachable shape.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('warns on every native array call at its own line and column', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    // Built with a foreach rather than array_map() so this file does not trip
    // the very sniff it tests.
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

/**
 * The standard says "whenever possible", so a justified native call is
 * tolerated: every report is a warning, and none is an error. Asserted
 * separately from the tuple test, which reads the warning list and would pass
 * unchanged if the sniff had never raised a warning at all.
 */
it('reports warnings rather than errors', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    expect($file->getWarningCount())->toBe(count(CONVERT_TO_COLLECTION_TUPLES))
        ->and($file->getErrors())->toBe([])
        ->and($file->getErrorCount())->toBe(0);
});

/**
 * Detection only. Rewriting a call into a pipeline changes the value's type
 * from array to Collection at every downstream use, so there is no safe
 * mechanical fix and no autofixed.php fixture.
 */
it('reports detection-only violations', function (): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'failing.php');

    expect($file->getFixableCount())->toBe(0);
});

/**
 * The flagged list is the sniff's own property, so replacing it replaces what
 * gets flagged. Run against failing.php — a file that is otherwise nothing but
 * violations — so the silence can only come from the shipped trio leaving the
 * configured list.
 */
it('flags nothing once the shipped functions leave the configured list', function () use (
    $analyzeWithArrayFunctions
): void {
    $file = $analyzeWithArrayFunctions('failing.php', ['array_walk' => 'each']);

    expect($file->getWarnings())->toBe([])
        ->and($file->getErrors())->toBe([]);
});

/**
 * The other half of the same property: a function the shipped list omits is
 * flagged once it is configured, and the message names the Collection method
 * the configuration gave it. passing.php's array_walk() call is silent under
 * the default list (asserted above), so this warning is the property's doing.
 *
 * Configured in upper case on purpose. PHP resolves function names
 * case-insensitively, so a ruleset that writes ARRAY_WALK must configure the
 * same function as one that writes array_walk — the sniff lowercases both the
 * configured names and the token it compares them against, and this is the
 * half of that pair the shouted call in failing.php does not reach.
 */
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

/**
 * PHPCS's ruleset parser accepts a plain comma-separated list as well as a
 * name=>method map, and hands the list over numerically keyed. The sniff
 * derives the Collection method for those entries by dropping the array_
 * prefix, so the natural list spelling configures the sniff instead of
 * silently switching it off.
 *
 * Driven through Ruleset::setSniffProperty() with the raw string a ruleset
 * would carry, rather than a hand-built numeric array: the numeric keys are
 * the parser's doing, and a test that assumed that shape would prove nothing
 * about the spelling this branch exists to support.
 */
it('accepts the plain-list property spelling PHPCS parses into numeric keys', function () use (
    $convertToCollectionMessages
): void {
    [$config, $ruleset] = buildRuleset([CONVERT_TO_COLLECTION], true);
    $sniffClass = $ruleset->sniffCodes[CONVERT_TO_COLLECTION];

    $ruleset->setSniffProperty(
        $sniffClass,
        'arrayFunctions[]',
        ['scope' => 'sniff', 'value' => 'array_values']
    );

    expect($ruleset->sniffs[$sniffClass]->arrayFunctions)->toBe([0 => 'array_values']);

    $file = new LocalFile(
        fixturePath(sniffFixtureDirectory(CONVERT_TO_COLLECTION), 'passing.php'),
        $ruleset,
        $config
    );
    $file->process();

    expect(warningTuples($file))->toBe([
        ['line' => 12, 'column' => 11, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_values() manipulates a native array; use collect()->values() instead',
    ]);
});
