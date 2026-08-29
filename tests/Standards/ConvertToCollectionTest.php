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
 * Every warning this sniff raises against the package's *own* source, as
 * file => line/column tuples. Reviewed one site at a time under #286.
 *
 * All 135 are native calls kept on purpose. The reason is a single
 * package-level fact, recorded once in rules.xml and in
 * docs/standards/arrays-convert-to-collection.md rather than 135 times across
 * 70 files: this package is a PHP_CodeSniffer standard with no
 * illuminate/collections dependency, so collect() does not exist here to call.
 * That is the plain-PHP context the warning severity exists for.
 *
 * Each site's own value was still read before it was left native, and PR #312
 * records what consumes it one site at a time for the 65 sites that existed
 * when that review ran. 63 of those 65 are consumed by something a Collection
 * does not satisfy: a strict in_array() haystack, an argument to another native
 * array function, sort() by reference, a declared array return, or a strict
 * comparison against an array literal. The other two —
 * tests/Standards/AvoidConditionalsTest.php:127 and
 * tests/Standards/LogicalGroupingsTest.php:206 — are only counted, which a
 * Collection satisfies through Countable, so their own usage neither blocks a
 * conversion nor argues for one; for those two the package-level fact is the
 * whole reason.
 *
 * The remaining 70 arrived with the sniffs that landed after #312 and are
 * pinned on the package-level fact alone — the same fact that carries those two,
 * and the only one that can carry any of them while collect() is absent. They
 * have not been walked one at a time the way #312 walked the first 65, so the
 * per-site claim above is deliberately scoped to that set rather than widened to
 * cover reviews nobody performed. Two of the 62 are the exception, read here
 * rather than pinned on the package-level fact alone: this file's own sibling,
 * tests/Standards/OnlyUseCollectionMethodsTest.php:390 and :436, each compares
 * array_map('strtolower', ...) against a native array by identity, which a
 * Collection does not satisfy.
 *
 * Pinned by file, line and column rather than by count: a count stays unchanged
 * when one site moves and another disappears. A native call added, moved or
 * removed anywhere under CleanCode/ or tests/ reddens the sweep below, so this
 * review keeps its value after #286 closes instead of decaying back into
 * ambient noise.
 *
 * Fixtures are out of scope for the reason the sweep command ignores them: a
 * fixture is deliberately non-compliant source, so its native calls are this
 * sniff's subject matter rather than the package's own code.
 */
const CONVERT_TO_COLLECTION_REVIEWED_SITES = [
    'CleanCode/Sniffs/ClearCode/ActionSingleEntryPointSniff.php' => [
        ['line' => 174, 'column' => 43],
    ],
    'CleanCode/Sniffs/ClearCode/JunkDrawerNamespaceSniff.php' => [
        ['line' => 90, 'column' => 24],
    ],
    'CleanCode/Sniffs/Conditionals/MappingArrayCandidateSniff.php' => [
        ['line' => 640, 'column' => 34],
    ],
    'CleanCode/Sniffs/Conditionals/TypeDiscriminatorDispatchSniff.php' => [
        ['line' => 818, 'column' => 18],
        ['line' => 838, 'column' => 28],
        ['line' => 873, 'column' => 31],
    ],
    'CleanCode/Sniffs/Controllers/NoCustomActionsSniff.php' => [
        ['line' => 178, 'column' => 34],
    ],
    'CleanCode/Sniffs/Controversial/SuperglobalsSniff.php' => [
        ['line' => 234, 'column' => 18],
    ],
    'CleanCode/Sniffs/DeadCode/UnusedFormalParameterSniff.php' => [
        ['line' => 963, 'column' => 16],
    ],
    'CleanCode/Sniffs/Functions/DisallowBooleanArgumentFlagSniff.php' => [
        ['line' => 171, 'column' => 18],
    ],
    'CleanCode/Sniffs/Livewire/ComponentMarkupSniff.php' => [
        ['line' => 1126, 'column' => 29],
    ],
    'CleanCode/Sniffs/Models/DisallowAlwaysOnEagerLoadingSniff.php' => [
        ['line' => 160, 'column' => 34],
    ],
    'CleanCode/Sniffs/Models/DisallowExternalPersistenceCallsSniff.php' => [
        ['line' => 71, 'column' => 31],
    ],
    'CleanCode/Sniffs/Models/MemberOrderingSniff.php' => [
        ['line' => 628, 'column' => 21],
        ['line' => 669, 'column' => 34],
    ],
    'CleanCode/Sniffs/Models/RequireLazyLoadingPreventionSniff.php' => [
        ['line' => 105, 'column' => 13],
    ],
    'CleanCode/Sniffs/Naming/BooleanGetMethodNameSniff.php' => [
        ['line' => 317, 'column' => 16],
    ],
    'CleanCode/Sniffs/Naming/LongClassNameSniff.php' => [
        ['line' => 148, 'column' => 16],
        ['line' => 149, 'column' => 13],
    ],
    'CleanCode/Sniffs/Naming/LongVariableSniff.php' => [
        ['line' => 259, 'column' => 29],
        ['line' => 543, 'column' => 16],
        ['line' => 544, 'column' => 13],
    ],
    'CleanCode/Sniffs/Naming/ModelNamingConventionsSniff.php' => [
        ['line' => 538, 'column' => 31],
        ['line' => 539, 'column' => 13],
    ],
    'CleanCode/Sniffs/Naming/RedundantNamespaceSuffixSniff.php' => [
        ['line' => 276, 'column' => 36],
    ],
    'CleanCode/Sniffs/Naming/ShortClassNameSniff.php' => [
        ['line' => 116, 'column' => 16],
        ['line' => 117, 'column' => 13],
    ],
    'CleanCode/Sniffs/Pattern/DisallowRepositoryClassesSniff.php' => [
        ['line' => 254, 'column' => 21],
    ],
    'CleanCode/Sniffs/Routes/ApiControllerNamespaceSniff.php' => [
        ['line' => 171, 'column' => 20],
    ],
    'CleanCode/Sniffs/Routes/NonInvokableSpecialActionSniff.php' => [
        ['line' => 411, 'column' => 22],
        ['line' => 423, 'column' => 13],
    ],
    'CleanCode/Sniffs/Testing/NoFirstPartyMocksSniff.php' => [
        ['line' => 874, 'column' => 44],
    ],
    'CleanCode/Sniffs/Testing/NoHttpFakesInIntegrationTestsSniff.php' => [
        ['line' => 532, 'column' => 21],
        ['line' => 536, 'column' => 31],
        ['line' => 573, 'column' => 44],
    ],
    'CleanCode/Sniffs/Testing/NoReflectionAccessSniff.php' => [
        ['line' => 278, 'column' => 44],
    ],
    'CleanCode/Sniffs/Testing/RequireTestFileSniff.php' => [
        ['line' => 274, 'column' => 49],
    ],
    'CleanCode/Sniffs/Testing/TestSuiteNamespaceSniff.php' => [
        ['line' => 338, 'column' => 20],
    ],
    'CleanCode/Sniffs/Testing/UnitTestExternalConcernsSniff.php' => [
        ['line' => 611, 'column' => 16],
        ['line' => 629, 'column' => 13],
    ],
    'tests/Contract/ShippedPackageSmokeTest.php' => [
        ['line' => 148, 'column' => 27],
    ],
    'tests/Helpers.php' => [
        ['line' => 666, 'column' => 66],
        ['line' => 835, 'column' => 36],
        ['line' => 1789, 'column' => 22],
    ],
    'tests/Rules/AvoidConditionalsRulesTest.php' => [
        ['line' => 45, 'column' => 25],
        ['line' => 109, 'column' => 25],
    ],
    'tests/Rules/BladeNoCodeFoundTest.php' => [
        ['line' => 33, 'column' => 29],
    ],
    'tests/Ruleset/NumberOfChildrenTest.php' => [
        ['line' => 47, 'column' => 33],
        ['line' => 67, 'column' => 59],
    ],
    'tests/Ruleset/TypeHintsRulesetTest.php' => [
        ['line' => 173, 'column' => 26],
    ],
    'tests/Sniffs.php' => [
        ['line' => 239, 'column' => 15],
    ],
    'tests/Standards/ActionMethodReturnTest.php' => [
        ['line' => 280, 'column' => 17],
    ],
    'tests/Standards/ArrayAccessorsTest.php' => [
        ['line' => 619, 'column' => 22],
        ['line' => 641, 'column' => 25],
    ],
    'tests/Standards/AvoidConditionalsTest.php' => [
        ['line' => 127, 'column' => 23],
    ],
    'tests/Standards/BlankLinesTest.php' => [
        ['line' => 91, 'column' => 42],
        ['line' => 117, 'column' => 42],
    ],
    'tests/Standards/BooleanOperatorSpacingTest.php' => [
        ['line' => 208, 'column' => 27],
    ],
    'tests/Standards/ComponentMarkupTest.php' => [
        ['line' => 400, 'column' => 16],
        ['line' => 402, 'column' => 9],
    ],
    'tests/Standards/DisallowAlwaysOnEagerLoadingTest.php' => [
        ['line' => 85, 'column' => 15],
        ['line' => 196, 'column' => 15],
    ],
    'tests/Standards/DisallowChainedPropertyFetchTest.php' => [
        ['line' => 462, 'column' => 20],
        ['line' => 469, 'column' => 29],
        ['line' => 532, 'column' => 58],
        ['line' => 533, 'column' => 9],
        ['line' => 535, 'column' => 17],
    ],
    'tests/Standards/DisallowClosureRoutesTest.php' => [
        ['line' => 109, 'column' => 18],
        ['line' => 187, 'column' => 17],
    ],
    'tests/Standards/DisallowCombinedConstructorTest.php' => [
        ['line' => 582, 'column' => 27],
    ],
    'tests/Standards/DisallowCountInLoopExpressionTest.php' => [
        ['line' => 112, 'column' => 17],
        ['line' => 270, 'column' => 17],
    ],
    'tests/Standards/DisallowDebugFunctionsTest.php' => [
        ['line' => 53, 'column' => 42],
    ],
    'tests/Standards/DisallowExitExpressionTest.php' => [
        ['line' => 65, 'column' => 18],
        ['line' => 141, 'column' => 18],
    ],
    'tests/Standards/DisallowNonResourceRoutesTest.php' => [
        ['line' => 61, 'column' => 44],
    ],
    'tests/Standards/DisallowRepositoryClassesTest.php' => [
        ['line' => 295, 'column' => 24],
        ['line' => 320, 'column' => 28],
        ['line' => 326, 'column' => 19],
    ],
    'tests/Standards/DisallowTypeIntrospectionTest.php' => [
        ['line' => 117, 'column' => 17],
        ['line' => 139, 'column' => 17],
        ['line' => 192, 'column' => 17],
        ['line' => 229, 'column' => 17],
        ['line' => 251, 'column' => 17],
        ['line' => 275, 'column' => 17],
        ['line' => 583, 'column' => 17],
    ],
    'tests/Standards/DuplicatedArrayKeyTest.php' => [
        ['line' => 88, 'column' => 42],
    ],
    'tests/Standards/ExcessiveMethodLengthTest.php' => [
        ['line' => 125, 'column' => 17],
        ['line' => 373, 'column' => 17],
    ],
    'tests/Standards/LogicalGroupingsTest.php' => [
        ['line' => 208, 'column' => 37],
        ['line' => 583, 'column' => 17],
        ['line' => 820, 'column' => 27],
        ['line' => 826, 'column' => 12],
    ],
    'tests/Standards/ManipulationOperatorPlacementTest.php' => [
        ['line' => 181, 'column' => 29],
    ],
    'tests/Standards/MemberOrderingTest.php' => [
        ['line' => 585, 'column' => 42],
    ],
    'tests/Standards/MethodNestingLevelTest.php' => [
        ['line' => 276, 'column' => 12],
        ['line' => 299, 'column' => 36],
    ],
    'tests/Standards/ModelMagicMethodLocationTest.php' => [
        ['line' => 113, 'column' => 12],
    ],
    'tests/Standards/MultiLineStatementIndentTest.php' => [
        ['line' => 445, 'column' => 28],
        ['line' => 458, 'column' => 28],
    ],
    'tests/Standards/NoHttpFakesInIntegrationTestsTest.php' => [
        ['line' => 65, 'column' => 44],
    ],
    'tests/Standards/NoInlineIfStatementsTest.php' => [
        ['line' => 50, 'column' => 42],
    ],
    'tests/Standards/NoInternetTraversalTest.php' => [
        ['line' => 74, 'column' => 44],
        ['line' => 411, 'column' => 15],
        ['line' => 463, 'column' => 14],
    ],
    'tests/Standards/NoLogicTest.php' => [
        ['line' => 455, 'column' => 15],
        ['line' => 526, 'column' => 15],
        ['line' => 761, 'column' => 20],
        ['line' => 797, 'column' => 15],
        ['line' => 801, 'column' => 10],
    ],
    'tests/Standards/NoProceduralCodeTest.php' => [
        ['line' => 142, 'column' => 57],
        ['line' => 176, 'column' => 57],
        ['line' => 446, 'column' => 13],
    ],
    'tests/Standards/NumberOfChildrenTest.php' => [
        ['line' => 491, 'column' => 33],
        ['line' => 499, 'column' => 66],
        ['line' => 574, 'column' => 33],
        ['line' => 578, 'column' => 27],
        ['line' => 586, 'column' => 59],
        ['line' => 749, 'column' => 75],
        ['line' => 789, 'column' => 75],
        ['line' => 859, 'column' => 25],
    ],
    'tests/Standards/OneThoughtPerLineTest.php' => [
        ['line' => 37, 'column' => 42],
    ],
    'tests/Standards/OnlyUseCollectionMethodsTest.php' => [
        ['line' => 390, 'column' => 28],
        ['line' => 436, 'column' => 28],
    ],
    'tests/Standards/OperatorLineBreakTest.php' => [
        ['line' => 110, 'column' => 33],
    ],
    'tests/Standards/PassiveOperatorSpacingTest.php' => [
        ['line' => 92, 'column' => 60],
        ['line' => 127, 'column' => 60],
    ],
    'tests/Standards/RequirePropertiesTest.php' => [
        ['line' => 198, 'column' => 25],
    ],
    'tests/Standards/ShortVariableTest.php' => [
        ['line' => 510, 'column' => 25],
    ],
    'tests/Standards/SuperglobalsTest.php' => [
        ['line' => 101, 'column' => 18],
        ['line' => 315, 'column' => 33],
        ['line' => 372, 'column' => 18],
        ['line' => 412, 'column' => 37],
        ['line' => 446, 'column' => 29],
        ['line' => 464, 'column' => 15],
        ['line' => 468, 'column' => 16],
        ['line' => 494, 'column' => 17],
        ['line' => 506, 'column' => 34],
    ],
    'tests/Standards/TooManyFieldsTest.php' => [
        ['line' => 208, 'column' => 25],
    ],
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
 * Every .php file the reviewed-sites sweep covers, relative to the package root
 * and sorted — the in-process equivalent of the two path arguments and the
 * fixture ignore pattern the #286 phpcs command sweeps with. Confirmed to agree
 * with that command: both report the same 127 file/line/column triples.
 *
 * A closure rather than a named function for the reason given on
 * $convertToCollectionMessages above.
 *
 * @var callable(): array<int, string>
 */
$convertToCollectionSweptFiles = static function (): array {
    $root = cleanCodeRoot();
    $paths = [];

    foreach (['CleanCode', 'tests'] as $tree) {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS)
        );

        // A foreach rather than an array_filter() pipeline, so this file adds no
        // site to the very set it pins.
        foreach ($entries as $entry) {
            $path = substr($entry->getPathname(), strlen($root) + 1);

            if (str_ends_with($path, '.php') === true && str_contains($path, '/fixtures/') === false) {
                $paths[] = $path;
            }
        }
    }

    sort($paths);

    return $paths;
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
 * parses rules.xml. The sniff is referenced by file path from the XML, so no
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
 * The sniff over the package's own source: the standing guard #286 exists to
 * leave behind.
 *
 * Every site is native on purpose and the reason is one package-level fact, so
 * the alternative — a comment at each of 127 call sites — would restate one
 * true thing 127 times and still not notice the next call the day someone adds
 * it. This pins the reviewed set instead, so an added, moved or deleted native
 * call is a red test rather than one more warning nobody reads.
 *
 * Two things make it fail closed rather than pass vacuously. Both trees have to
 * be reached, asserted through two files that carry no reviewed site at all —
 * so the walk is proven to look at clean files too, which is what makes "a new
 * native call anywhere reddens this" true rather than merely "a changed line
 * number in an already-flagged file reddens this". And every swept file has to
 * tokenize: a path PHP_CodeSniffer cannot read yields no tokens and therefore no
 * warnings, which is indistinguishable from a clean file in the comparison
 * below.
 *
 * Confirmed non-vacuous by mutation rather than by reading: converting any one
 * pinned site to a foreach, moving one down a line, or adding an array_map()
 * call to an unpinned file each reddens the final comparison.
 */
it('reports only the reviewed sites across the package source', function () use ($convertToCollectionSweptFiles): void {
    $swept = $convertToCollectionSweptFiles();
    $found = [];

    foreach ($swept as $relative) {
        $file = analyzeWithSniffs([CONVERT_TO_COLLECTION], cleanCodeRoot() . '/' . $relative);

        expect($file->numTokens)->toBeGreaterThan(0, "{$relative} produced no tokens");

        foreach (warningTuples($file) as $tuple) {
            $found[$relative][] = ['line' => $tuple['line'], 'column' => $tuple['column']];
        }
    }

    expect($swept)->toContain('CleanCode/Sniffs/Arrays/ConvertToCollectionSniff.php')
        ->and($swept)->toContain('tests/Standards/ConvertToCollectionTest.php')
        ->and($found)->toBe(CONVERT_TO_COLLECTION_REVIEWED_SITES);
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
