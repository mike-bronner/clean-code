<?php

/**
 * Tests the custom CleanCode.Collections.OnlyUseCollectionMethods sniff
 * (Collections: Only Use Collection Methods, #28). Fixtures live in
 * tests/fixtures/OnlyUseCollectionMethodsSniff/ and follow the three-fixture
 * contract: passing.php is clean, failing.php carries every mapped function
 * plus the shapes the fixer must decline, and autofixed.php is phpcbf's output
 * for failing.php. imported-function.php is a fourth, descriptive fixture — it
 * needs its own file because a `use function … as count;` import rebinds the
 * name for the whole file, which cannot coexist with failing.php's builtin
 * calls.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Collections\OnlyUseCollectionMethodsSniff;

const ONLY_USE_COLLECTION_METHODS = 'CleanCode.Collections.OnlyUseCollectionMethods';

/**
 * Every violation carries the same code, so only the message distinguishes one
 * mapping from another. Matching it against this pattern is what turns the
 * sniff's function => method table into something the suite verifies.
 */
const ONLY_USE_COLLECTION_METHODS_MESSAGE = '/^Use the Collection method (\w+)\(\) instead of'
    . ' the generic PHP function (\w+)\(\) on a Collection$/';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ONLY_USE_COLLECTION_METHODS);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A `use function … as count;` import rebinds the name for the whole file, so
 * an unqualified `count($collection)` is the import rather than the builtin the
 * sniff maps — and `count` is one of the functions the fixer rewrites, so
 * trusting the name alone turns a working call into a silently different
 * answer.
 *
 * Both directions are pinned from one fixture: the two shadowed calls stay
 * silent, and the fully-qualified `\count()` on line 33 is the builtin again
 * and stays reported *and* fixable. Asserting only the silence would pass just
 * as well if the sniff stopped reporting the file altogether.
 */
it('lets an imported function shadow the builtin but not a qualified call', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-function.php');

    expect(violationSourcesByLine($file->getErrors()))
        ->toBe([33 => [ONLY_USE_COLLECTION_METHODS . '.Found']])
        ->and(violationFixableLines($file->getErrors()))->toBe([33]);
});

it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        13 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        14 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        15 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        16 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        17 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        18 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        19 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        20 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        31 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        32 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        33 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        40 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        41 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        42 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        49 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        50 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        58 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        67 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        68 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        69 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        70 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        71 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        81 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        82 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        92 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        93 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        103 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        104 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        121 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        122 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        146 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        157 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        166 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
        180 => [ONLY_USE_COLLECTION_METHODS . '.Found'],
    ]);
});

/**
 * The violation code is the same for all seventeen mapped functions, so the
 * message is the only thing that proves the sniff named the right replacement.
 * Every mapping in GENERIC_FUNCTIONS appears below.
 */
it('names the Collection method that replaces each generic function', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');
    $mappings = [];

    foreach (violationMessagesByLine($file->getErrors()) as $line => $messages) {
        foreach ($messages as $message) {
            // An unmatched message fails outright rather than collapsing to an
            // empty pair, so a reworded message cannot pass this vacuously.
            expect($message)->toMatch(ONLY_USE_COLLECTION_METHODS_MESSAGE);
            preg_match(ONLY_USE_COLLECTION_METHODS_MESSAGE, $message, $matches);

            $mappings[$line] = $matches[2] . '() => ' . $matches[1] . '()';
        }
    }

    expect($mappings)->toBe([
        13 => 'array_map() => map()',
        14 => 'array_filter() => filter()',
        15 => 'array_reduce() => reduce()',
        16 => 'array_keys() => keys()',
        17 => 'array_values() => values()',
        18 => 'count() => count()',
        19 => 'in_array() => contains()',
        20 => 'implode() => implode()',
        31 => 'array_sum() => sum()',
        32 => 'array_slice() => slice()',
        33 => 'array_unique() => unique()',
        40 => 'count() => count()',
        41 => 'count() => count()',
        42 => 'array_merge() => merge()',
        49 => 'count() => count()',
        50 => 'array_values() => values()',
        58 => 'count() => count()',
        67 => 'array_diff() => diff()',
        68 => 'array_intersect() => intersect()',
        69 => 'array_key_exists() => has()',
        70 => 'array_search() => search()',
        71 => 'join() => implode()',
        81 => 'count() => count()',
        82 => 'count() => count()',
        92 => 'count() => count()',
        93 => 'count() => count()',
        103 => 'count() => count()',
        104 => 'count() => count()',
        121 => 'count() => count()',
        122 => 'array_sum() => sum()',
        146 => 'count() => count()',
        157 => 'count() => count()',
        166 => 'count() => count()',
        180 => 'count() => count()',
    ]);
});

/**
 * Fixability is asserted line by line rather than as a total, because the two
 * conditions that withhold it are invisible in a count: a call is fixable only
 * when it is a 1:1 swap the fixer knows how to spell *and* its receiver is a
 * Collection the tokens prove outright.
 *
 * That second condition is what keeps TERMINAL_METHODS out of the fixer's path.
 * Lines 103-104 chain off a Collection, so the sniff types them by asking
 * whether the chain's last method is on that hand-curated list — fine for a
 * report, not something to rewrite source on. They must report and stay
 * unfixable; if they ever appear below, an incomplete list can fatal a codebase
 * again.
 *
 * Lines 121-122 are the same collapse applied to by-reference mutation: the
 * receiver was handed bare to a call that may carry a `&$parameter`, so it is
 * proven at its assignment but not at the call site. Reported, never rewritten.
 */
it('offers a fix only for provably-typed receivers', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(34)
        ->and(violationFixableLines($file->getErrors()))
        ->toBe([18, 31, 49, 81, 82, 92, 93, 146, 157, 166, 180]);
});

/**
 * TERMINAL_METHODS decides whether a chain is still a Collection, and it fails
 * in the dangerous direction: a method missing from it is assumed to return a
 * Collection, so every omission is a false positive. It is also a hand-curated
 * mirror of a framework API that changes without this package, which is how
 * random() slipped in unnoticed.
 *
 * The fixer no longer consults it (see the fixability test above), so an
 * omission now costs a warning rather than a rewrite — but only five of its
 * sixty entries have behavioural coverage, and without this the other
 * fifty-five could be deleted with the suite still green. Pinning the key set
 * makes every edit to the constant a deliberate, reviewed one.
 */
it('pins the terminal-method list', function (): void {
    $terminal = (new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('TERMINAL_METHODS');

    expect(array_keys($terminal))->toBe([
        'after', 'all', 'average', 'avg', 'before', 'contains', 'containsoneitem', 'containsstrict', 'count',
        'doesntcontain', 'every', 'find', 'first', 'firstorfail', 'firstwhere', 'get', 'getiterator',
        'getorput', 'has', 'hasany', 'implode', 'isempty', 'isnotempty', 'join', 'jsonserialize', 'last',
        'max', 'median', 'min', 'mode', 'modelkeys', 'offsetexists', 'offsetget', 'offsetset', 'offsetunset',
        'percentage', 'pipe', 'pipeinto', 'pipethrough', 'pop', 'pull', 'random', 'reduce', 'reducespread',
        'reducewithkeys', 'search', 'shift', 'sole', 'some', 'sum', 'toarray', 'tojson', 'toquery', 'unless',
        'unlessempty', 'unlessnotempty', 'value', 'when', 'whenempty', 'whennotempty',
    ]);
});

/**
 * Lookups lower-case the method name before checking the list, so an entry
 * carrying a capital can never match. Keeping the list sorted is what makes a
 * missing entry visible to the next person auditing it against the framework.
 */
it('keeps the terminal-method list lower-cased and sorted', function (): void {
    $keys = array_keys((new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('TERMINAL_METHODS'));
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted)
        ->and($keys)->toBe(array_map('strtolower', $keys));
});
