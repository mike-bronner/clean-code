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
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;

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
    ['line' => 44, 'column' => 23],
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
 * Runs a fixture with the flagged-function list replaced, by assigning the
 * public property directly. That covers what the *sniff* does with a replaced
 * list; what a ruleset's own <property>/<element> parser hands it is a separate
 * question, pinned through a real ruleset file by $analyzeThroughRulesetFile
 * below.
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

/**
 * Runs a fixture through a ruleset built from an actual ruleset XML file in the
 * sniff's own fixture directory, so PHPCS's <property>/<element> reader is what
 * configures the sniff — the route a consuming project's ruleset takes.
 *
 * Builds its own Config rather than going through buildRuleset(), which always
 * parses CleanCode/ruleset.xml. The sniff is referenced by file path from the XML, so no
 * installed_paths entry is needed; the fresh ConfigDouble resets PHPCS's static
 * config state, exactly as tests/Ruleset/UndefinedVariableTest.php does, so
 * nothing here leaks into a memoised ruleset later in the run.
 *
 * @var callable(string, string): LocalFile
 */
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

/**
 * passing.php pairs the Collection pipelines the standard asks for with every
 * near-miss shape the sniff must stay silent on — the configured names reached
 * through an object operator, a nullsafe operator and a double colon, declared
 * as a function, a method and a static method, used as a type hint and as a
 * bare constant (the two shapes that reach the sniff without an open
 * parenthesis after them), as a string, a property, a class being
 * instantiated — bare, fully qualified and namespace-relative — declared by
 * reference as a function, a method and a static method, and behind a
 * qualified namespace prefix, plus four native array functions outside the
 * configured list. Each of those is one of the sniff's
 * guards, so the fixture's silence is a verdict about them rather than merely
 * the absence of an array call.
 *
 * The three keyword shapes that carry something between the keyword and the
 * name — new \array_reduce(), new namespace\array_map() and the &-returning
 * declarations — are the ones a test of the keyword alone would miss. Each of
 * their spellings is flagged elsewhere in the suite without its keyword
 * (\array_map() in failing.php, namespace\array_map() in the global block of
 * namespaced-blocks.php, the plain names throughout), so their silence here is
 * the keyword's doing rather than the spelling's.
 *
 * The namespace-relative spelling is deliberately not among them: this file
 * declares no namespace, so namespace\array_filter() names the global function
 * here and belongs in failing.php. namespaced.php carries it as a negative.
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
 * PHPCS's ruleset parser accepts an <element> with no key as well as a keyed
 * one, and hands the keyless entries over numerically keyed rather than as a
 * name => method map. The sniff derives the Collection method for those by
 * dropping the array_ prefix, so the natural list spelling configures the
 * sniff instead of silently switching it off.
 *
 * Driven through a real ruleset XML file rather than
 * Ruleset::setSniffProperty(), which is the entry point inline phpcs:set
 * annotations use and reaches the sniff by a separately implemented route. A
 * ruleset's own <property> tag is parsed inside Ruleset::processRule() and
 * handed over pre-parsed, so only a ruleset file exercises the path a
 * consuming project actually takes.
 *
 * The keyless entry is shouted on purpose. PHP resolves function names
 * case-insensitively, so ARRAY_VALUES must configure array_values *and* name
 * values() — the array_ prefix is dropped from the lowercased name, not the
 * raw one, and a message reading collect()->ARRAY_VALUES() is the regression
 * this pins. The keyed entry beside it covers the other half: a function whose
 * Collection name the prefix rule cannot derive.
 */
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

/**
 * namespace\array_filter() resolves against the namespace in force where it is
 * written, so the verdict belongs to the file rather than to the call. Where no
 * namespace has been declared the namespace in force is the global one, which
 * makes the call the native function — that is failing.php's last line, pinned
 * in the tuple list above.
 *
 * namespaced.php is the other half: the same spelling under namespace
 * App\Support;, where it names a different symbol and stays silent. The bare
 * array_map() beside it keeps that silence honest — a sniff that gave up on
 * namespaced files wholesale would leave both alone and pass a silence-only
 * assertion. The fixture spells the namespace-relative call twice so the
 * second one has a namespace\ *operator* between it and the declaration: read
 * as a declaration, that keyword would answer the question with a token that
 * declares nothing.
 */
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

/**
 * Whether the file declares a namespace anywhere is not the question; which
 * declaration is in force at the call is. namespaced-blocks.php pairs a named
 * block with a global one, so a check that only asked "does this file declare
 * a namespace" would wrongly excuse the global block's namespace\array_map()
 * — while the named block's call has to stay silent all the same. The global
 * block carries two calls for the same reason namespaced.php does: the second
 * has a namespace\ operator standing between it and the block that governs it.
 */
it('flags a namespace-relative call inside a global namespace block', function () use (
    $convertToCollectionMessages
): void {
    $file = analyzeFixture(CONVERT_TO_COLLECTION, 'namespaced-blocks.php');

    expect(warningTuples($file))->toBe([
        ['line' => 17, 'column' => 24, 'source' => CONVERT_TO_COLLECTION . '.Found'],
        ['line' => 22, 'column' => 25, 'source' => CONVERT_TO_COLLECTION . '.Found'],
    ])->and($convertToCollectionMessages($file))->toBe([
        'array_map() manipulates a native array; use collect()->map() instead',
        'array_filter() manipulates a native array; use collect()->filter() instead',
    ]);
});
