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
 * sibling standards land in CleanCode/ruleset.xml.
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

/**
 * The compliant fixture is where every shape the sniff must stay silent on is
 * pinned, so several of its cases are only meaningful as near misses:
 *
 * - A method-local `static $data;` still rebinds the name. The exemption that
 *   keeps an untyped static *property* from retiring a same-named Collection is
 *   for a declaration sitting directly in a class body — never one inside a
 *   method, whose enclosing class encloses its locals too. A guard written as
 *   "any class among the conditions" satisfies failing.php's lines 222-223 and
 *   breaks here instead.
 * - A declaration named after a mapped function (`function count($items)`, and
 *   `function &implode($items)` behind a reference marker) is not a call. Both
 *   sit in a scope holding a tracked Collection of the parameter's name, so a
 *   preceder check that missed either spelling reports the declaration — and
 *   phpcbf rewrites it into `function &$items->count()`, which will not parse.
 */
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

    expect(violationTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 51, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 63, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
    ]);
});

/**
 * The by-value exemption that keeps `count($c)` from escaping its own receiver
 * is keyed on the *global* function of that name, so it has to consult the same
 * shadow check the reporting path does. A shadowed `count($collection)` (line
 * 49) and a `$aggregator->count($collection)` method of the same name (line 61)
 * are both userland code free to declare `&$items`, so the `array_sum()` after
 * each one is reported but no longer rewritten.
 *
 * Line 33's still-fixable `\count()` is the other half of the pin: a check that
 * escaped every argument of every call would satisfy the two unfixable lines
 * and lose that one.
 */
it('escapes a variable handed to a shadowed call spelled like a mapped function', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-function.php');

    expect(violationFixableLines($file->getErrors()))->toBe([33]);
});

/**
 * `collect()` is the one Collection origin recognised by bare name, so it is
 * the one a `use function … as collect;` import can take away. A shadowed
 * `collect($rows)` returns whatever the import returns, so the `count()` around
 * it (line 23) is neither reported nor fixable — rewriting it would spell
 * `collect($rows)->count()` against a plain array.
 *
 * Line 33's qualified `\collect()` is a Collection origin again, and pins the
 * other direction: asserting the silence alone would pass just as well if the
 * sniff stopped recognising `collect()` altogether.
 */
it('lets an imported collect() shadow the helper but not a qualified call', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-collect.php');

    expect(violationTuples($file))
        ->toBe([['line' => 33, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found']])
        ->and(violationFixableLines($file->getErrors()))->toBe([33]);
});

/**
 * The whole census, pinned by line, column and source rather than by line
 * alone. The column is the mapped function's own name token, which is what
 * makes it worth asserting: a line can carry the call plus the receiver that
 * feeds it, so a report landing on the wrong token of the right line is a real
 * regression that a line-keyed assertion cannot see. Shifting every report one
 * token left leaves the line map byte-identical and reddens this.
 *
 * Column also discriminates *within* a line here — 92, 93, 297 and the nested
 * shapes report well past the indent — so the values are evidence rather than
 * a repeated constant.
 */
it('flags every violation at its own line and column with the expected code', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 14, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 15, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 16, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 17, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 18, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 19, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 20, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 31, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 32, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 33, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 40, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 41, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 42, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 49, 'column' => 10, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 50, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 58, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 67, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 68, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 69, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 70, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 71, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 81, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 82, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 92, 'column' => 46, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 93, 'column' => 30, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 103, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 104, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 121, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 122, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 146, 'column' => 21, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 157, 'column' => 22, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 166, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 180, 'column' => 19, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 190, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 191, 'column' => 11, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 198, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 205, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 222, 'column' => 16, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 223, 'column' => 18, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 231, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 236, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 259, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 266, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 279, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 287, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 297, 'column' => 49, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 316, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 325, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 335, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 344, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 359, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 370, 'column' => 16, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 377, 'column' => 16, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 396, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 406, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 418, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 430, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
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
        190 => 'count() => count()',
        191 => 'count() => count()',
        198 => 'count() => count()',
        205 => 'count() => count()',
        222 => 'count() => count()',
        223 => 'count() => count()',
        231 => 'count() => count()',
        236 => 'count() => count()',
        259 => 'count() => count()',
        266 => 'count() => count()',
        279 => 'count() => count()',
        287 => 'count() => count()',
        297 => 'count() => count()',
        316 => 'count() => count()',
        325 => 'count() => count()',
        335 => 'count() => count()',
        344 => 'count() => count()',
        359 => 'count() => count()',
        370 => 'count() => count()',
        377 => 'count() => count()',
        396 => 'count() => count()',
        406 => 'count() => count()',
        418 => 'count() => count()',
        430 => 'count() => count()',
    ]);
});

/**
 * Fixability is asserted line by line rather than as a total, because the two
 * conditions that withhold it are invisible in a count: a call is fixable only
 * when it is a 1:1 swap the fixer knows how to spell *and* its receiver is a
 * Collection the tokens prove outright.
 *
 * That second condition is what keeps TERMINAL_METHODS out of the fixer's path.
 * A chained receiver is typed for the *fix* from CHAINABLE_METHODS, which fails
 * closed, and never from TERMINAL_METHODS, which fails open. The two directions
 * are pinned against each other here, because a regression that let the fixer
 * fall back on TERMINAL_METHODS would still leave the total unmoved:
 *
 * - Lines 40-41, 103-104 and 198 chain only through methods whose Collection
 *   return is contractual, so they are proven and fixable. 198 runs three links
 *   deep, so a check that only read the chain's last link cannot pass it.
 * - Lines 190-191 chain through a method neither list knows (`chunk()`, which
 *   returns a Collection of Collections, and a fabricated one). They stay
 *   reported, because TERMINAL_METHODS assumes a Collection — and unfixable,
 *   because CHAINABLE_METHODS does not. That is the whole safety argument, and
 *   it is the assertion that fails if the polarity is ever collapsed to one
 *   list.
 * - Line 205 chains through `?->`, which can yield null however sound the
 *   method after it is.
 *
 * Lines 121-122 are the same collapse applied to by-reference mutation: the
 * receiver was handed bare to a call that may carry a `&$parameter`, so it is
 * proven at its assignment but not at the call site. Reported, never rewritten.
 *
 * The same polarity runs through what a *declaration* proves, which is the
 * other half of the list above:
 *
 * - Line 231 (`Collection|ArrayObject`) and line 259 (`Collection|array`) are
 *   unions, so the receiver holds one member or the other. Reported, because a
 *   Collection is among them; unfixable, because the rest are not. Line 266's
 *   `?Collection` is the same choice with null as the other member.
 * - Line 236 (`Collection&Countable`) is an intersection, so the receiver is
 *   every member at once. One Collection member proves it, and it stays
 *   fixable — this is the line that fails if unions and intersections are ever
 *   read as the same connector again.
 * - Lines 279 and 287 are one expression written two ways: assigned to a
 *   variable first, then inline. Both chain through a method neither list
 *   knows, so both are reported and neither is fixable. They are asserted as a
 *   pair because a variable used to launder the fail-open reading into the
 *   fixer — 287 declined while 279, the same call, was rewritten.
 *
 * A variadic parameter appears in none of this: `Collection ...$items` binds an
 * array, so it is not reported at all and is pinned in passing.php instead.
 *
 * Lines 316, 325, 335, 344, 359, 370 and 377 are the same fail-closed rule
 * applied to the *callee* rather than the receiver, one line per member of
 * CALLABLE_EXPRESSION_ENDERS: an IIFE, an indexed callable, a returned closure,
 * a dynamic method name, and the `new class`/`new self`/`new static`
 * constructors. None of them carries a name the sniff can resolve, so none can
 * be shown to take its argument by value, and each may rebind the receiver
 * through a `&$parameter` before the `count()` below it runs. They are asserted
 * as reported-but-unfixable, and they are asserted individually because the
 * admission set is hand-written: any one member dropped from it silently
 * restores the rewrite for that shape alone, which no total would show.
 *
 * Lines 396 and 406 are that admission set read the other way, and they are the
 * only two entries below that a *widening* of it breaks. Two of its members are
 * closing brackets that a block also ends — the `}` of an `if` body, the `)` of
 * a brace-less one's condition — and PHP puts no semicolon after either, so
 * each can sit directly in front of the parenthesis opening the next statement.
 * Matching the token alone reads an ordinary guard clause as a callable
 * expression and withholds the rewrite from every line after it. Both stay
 * fixable, and they are the mirror of the seven above: those fail if a member is
 * dropped, these fail if a member is trusted without asking what it closes.
 */
it('offers a fix only for provably-typed receivers', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(58)
        ->and(violationFixableLines($file->getErrors()))
        ->toBe([18, 31, 40, 41, 49, 81, 82, 92, 93, 103, 104, 146, 157, 166, 180, 198, 222, 223, 236, 396, 406]);
});

/**
 * CHAINABLE_METHODS is the list the *fixer* types a chained receiver from, so it
 * fails closed: a method missing from it costs a declined fix rather than a
 * rewrite. That is the safe direction, but only four of its entries
 * (`filter`, `map`, `unique`, `values`) are ever exercised by a fixture, so the
 * other twenty could be deleted or mistyped with the suite still green.
 *
 * Sibling TERMINAL_METHODS is pinned the same way below, and for the same
 * reason: an unpinned hand-curated constant is how random() slipped in
 * unnoticed. Pinning the key set makes every edit a deliberate, reviewed one.
 */
it('pins the chainable-method list', function (): void {
    $chainable = (new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('CHAINABLE_METHODS');

    expect(array_keys($chainable))->toBe([
        'diff', 'except', 'filter', 'flatten', 'flip', 'intersect', 'keys', 'map', 'merge', 'only', 'pluck',
        'reject', 'reverse', 'slice', 'sort', 'sortby', 'sortbydesc', 'sortdesc', 'take', 'unique', 'values',
        'where', 'wherein', 'wherenotin',
    ]);
});

/**
 * The same lower-cased-and-sorted invariant TERMINAL_METHODS carries, for the
 * same reason: lookups lower-case the method name first, so an entry holding a
 * capital can never match, and sorted order is what makes a missing entry
 * visible to whoever audits the list against the framework next.
 */
it('keeps the chainable-method list lower-cased and sorted', function (): void {
    $keys = array_keys((new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('CHAINABLE_METHODS'));
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted)
        ->and($keys)->toBe(array_map('strtolower', $keys));
});

/**
 * TERMINAL_METHODS decides whether a chain is still a Collection, and it fails
 * in the dangerous direction: a method missing from it is assumed to return a
 * Collection, so every omission is a false positive. It is also a hand-curated
 * mirror of a framework API that changes without this package, which is how
 * random() slipped in unnoticed.
 *
 * The fixer no longer consults it (see the fixability test above), so an
 * omission now costs a warning rather than a rewrite — but only nine of its
 * sixty entries have behavioural coverage, and without this the other
 * fifty-one could be deleted with the suite still green. Pinning the key set
 * makes every edit to the constant a deliberate, reviewed one.
 *
 * The nine are `all`, `getOrPut`, `mode`, `modelKeys`, `random`,
 * `reduceWithKeys`, `toArray`, `unlessEmpty` and `unlessNotEmpty` — each
 * counted by deleting its entry and rerunning the suite with this test
 * excluded, not by reading the fixtures.
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

/**
 * A half-written call is the normal state of a file being edited, and the
 * tokenizer leaves `parenthesis_closer` unset on a parenthesis it never sees
 * closed. Reading it anyway raises an "Undefined array key" diagnostic, which
 * the `phpcs` binary turns into `Internal.Exception` and *aborts the whole
 * file* on — so one unfinished line at the bottom silently took every finding
 * above it down with it, which is the worst failure direction available to a
 * linter: it looks like a clean file.
 *
 * The error handler is what makes this assertable in-process. `LocalFile` does
 * not install the binary's handler, so the diagnostic stays a plain PHP warning
 * here and the violations survive it — collecting it is the only way this suite
 * can see the defect the binary aborts on. `TooManyMethodsTest`'s
 * "emits no PHP warning" test uses the same technique for the same reason.
 *
 * The fixture pins both halves against each other. Line 6 is a complete,
 * fixable violation and must survive; line 8 is the unterminated call and must
 * stay silent. Asserting the silence alone would pass just as well if the file
 * stopped being processed altogether.
 */
it('says nothing about an unterminated call, and still reports the line above it', function (): void {
    $diagnostics = [];

    set_error_handler(static function (int $errno, string $message) use (&$diagnostics): bool {
        $diagnostics[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'unterminated-call.php');
    } finally {
        restore_error_handler();
    }

    expect($diagnostics)->toBe([])
        ->and(violationTuples($file))
        ->toBe([['line' => 6, 'column' => 10, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found']])
        ->and(warningTuples($file))->toBe([])
        ->and(violationFixableLines($file->getErrors()))->toBe([6]);
});

/**
 * The rewrite is assembled from the argument's own token string, so a comment
 * written inside the call travels into it. A trailing line comment is the shape
 * that corrupts: trimming the argument takes away the newline that *ends* the
 * comment and leaves its `//` marker, so the appended `->count()` and every
 * token after it — the statement's own semicolon included — landed inside a
 * comment that never closed. `phpcbf` turned source that parsed into source
 * that did not.
 *
 * Three things are asserted together, because each one alone survives a
 * different regression:
 *
 * - Both lines are still **reported**. Declining a fix must not become silence,
 *   and a gate written into the reporting path instead of the fixing one would
 *   pass every other assertion here.
 * - Both are declined by the **fixer**, and the two lines come back from it
 *   byte for byte.
 * - The fixer's output over the whole fixture **parses**. This is the one that
 *   fails on the original defect, and the reason it is worth a subprocess: the
 *   corrupted output tokenises perfectly well — as a comment — so the contract
 *   sweep's tokenizer assertion cannot see it, and neither can any assertion
 *   that only reads the fixed string.
 *
 * Line 430's block comment would have survived the rewrite intact. It is
 * declined by the same rule rather than by a shape of its own, and asserted
 * here so that narrowing the rule to `//` alone shows up as a failure instead
 * of as a silent widening of what the fixer will touch.
 */
it('declines to rewrite a call whose argument carries a comment', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');
    $errors = $file->getErrors();
    $fixed = autofixedContents($file);
    $path = tempnam(sys_get_temp_dir(), 'only-use-collection-methods-');

    // A parse check that could not write its input would otherwise pass on an
    // empty file, which php -l calls clean.
    if ($path === false) {
        throw new RuntimeException('could not write the fixed source out for the parse check');
    }

    file_put_contents($path, $fixed);

    try {
        [$lint, , $status] = runOutsidePackage(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path));
    } finally {
        unlink($path);
    }

    expect(violationTuples($file))
        ->toContain(['line' => 418, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'])
        ->toContain(['line' => 430, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'])
        ->and(violationFixableLines($errors))->not->toContain(418)
        ->and(violationFixableLines($errors))->not->toContain(430)
        ->and($fixed)->toContain('        $data // the collection being counted')
        ->and($fixed)->toContain('return count($data /* the collection being counted */);')
        ->and($lint)->toContain('No syntax errors detected')
        ->and($status)->toBe(0);
});
