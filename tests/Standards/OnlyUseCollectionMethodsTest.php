<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Collections\OnlyUseCollectionMethodsSniff;

const ONLY_USE_COLLECTION_METHODS = 'CleanCode.Collections.OnlyUseCollectionMethods';

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

it('lets an imported function shadow the builtin but not a qualified call', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-function.php');

    expect(violationTuples($file))->toBe([
        ['line' => 33, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 51, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
        ['line' => 63, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
    ]);
});

it('escapes a variable handed to a shadowed call spelled like a mapped function', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-function.php');

    expect(violationFixableLines($file->getErrors()))->toBe([33]);
});

it('lets an imported collect() shadow the helper but not a qualified call', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'imported-collect.php');

    expect(violationTuples($file))
        ->toBe([['line' => 33, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found']])
        ->and(violationFixableLines($file->getErrors()))->toBe([33]);
});

it('resolves an import alias for a bare class name only', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'qualified-alias.php');

    expect(violationTuples($file))
        ->toBe([['line' => 23, 'column' => 12, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found']]);
});

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
        ['line' => 49, 'column' => 9, 'source' => ONLY_USE_COLLECTION_METHODS . '.Found'],
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

it('names the Collection method that replaces each generic function', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');
    $mappings = [];

    foreach (violationMessagesByLine($file->getErrors()) as $line => $messages) {
        foreach ($messages as $message) {
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

it('offers a fix only for provably-typed receivers', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(58)
        ->and(violationFixableLines($file->getErrors()))
        ->toBe([18, 31, 40, 41, 49, 81, 82, 92, 93, 103, 104, 146, 157, 166, 180, 198, 222, 223, 236, 396, 406]);
});

it('pins the chainable-method list', function (): void {
    $chainable = (new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('CHAINABLE_METHODS');

    expect(array_keys($chainable))->toBe([
        'diff', 'except', 'filter', 'flatten', 'flip', 'intersect', 'keys', 'map', 'merge', 'only', 'pluck',
        'reject', 'reverse', 'slice', 'sort', 'sortby', 'sortbydesc', 'sortdesc', 'take', 'unique', 'values',
        'where', 'wherein', 'wherenotin',
    ]);
});

it('keeps the chainable-method list lower-cased and sorted', function (): void {
    $keys = array_keys((new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('CHAINABLE_METHODS'));
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted)
        ->and($keys)->toBe(array_map('strtolower', $keys));
});

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

it('keeps the terminal-method list lower-cased and sorted', function (): void {
    $keys = array_keys((new ReflectionClass(OnlyUseCollectionMethodsSniff::class))->getConstant('TERMINAL_METHODS'));
    $sorted = $keys;
    sort($sorted);

    expect($keys)->toBe($sorted)
        ->and($keys)->toBe(array_map('strtolower', $keys));
});

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

it('declines to rewrite a call whose argument carries a comment', function (): void {
    $file = analyzeFixture(ONLY_USE_COLLECTION_METHODS, 'failing.php');
    $errors = $file->getErrors();
    $fixed = autofixedContents($file);
    $path = tempnam(sys_get_temp_dir(), 'only-use-collection-methods-');

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
