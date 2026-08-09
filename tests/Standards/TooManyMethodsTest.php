<?php

/**
 * Tests the custom CleanCode.CodeSize.TooManyMethods sniff (PHPMD
 * CodeSize/TooManyMethods, #80). Fixtures live in
 * tests/fixtures/TooManyMethodsSniff/.
 *
 * The subject is a count, so every fixture here is written one method per
 * line: the number under test is then countable by eye against the assertion,
 * which a fixture in full brace style would bury under a hundred lines of
 * empty bodies. Fixtures are excluded from `composer lint`, so the compact
 * layout costs the PSR-12 self-lint nothing.
 *
 * Each fixture sits *on* a boundary rather than comfortably past it, so that
 * an assertion of silence is never satisfied by a sniff that has simply fallen
 * quiet: move one method across the line the fixture pins and the same file
 * reports. Where a fixture pins a filter (the ignore pattern, the scoping of
 * nested declarations), it carries enough excluded methods that switching the
 * filter off pushes the class past the threshold.
 *
 * The rule is detection-only, matching PHPMD, so there is no autofixed
 * fixture: splitting a class is a decision about where each behaviour belongs.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */

declare(strict_types=1);

const TOO_MANY_METHODS = 'CleanCode.CodeSize.TooManyMethods';

const TOO_MANY_METHODS_ERROR = TOO_MANY_METHODS . '.MaxExceeded';

const TOO_MANY_METHODS_PATTERN_ERROR = TOO_MANY_METHODS . '.InvalidIgnorePattern';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_METHODS);
});

/**
 * The master ruleset configures both properties explicitly, so a consumer
 * reading rules.xml sees the thresholds it is running under. This asserts the
 * values that arrive on the sniff *through that file*, which is the only way
 * to catch a typo in the XML — the defaults on the class would answer for it
 * otherwise.
 */
it('carries PHPMD\'s defaults through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TOO_MANY_METHODS]];

    expect($sniff->maxmethods)->toBe(25)
        ->and($sniff->ignorepattern)->toBe('(^(set|get|is|has|with))i');
});

/**
 * Everything the sniff must leave alone, in one file:
 *
 * - line 3, `ExactlyAtTheMaximum` — 25 counted methods plus five accessors
 *   (`getName`, `setName`, `isReady`, `hasItems`, `withTax`). PHPMD returns on
 *   `count <= maxmethods`, so a class holding exactly the maximum is
 *   compliant; this is the silent half of that boundary, and `failing.php` is
 *   the other. It also pins the accessor filter from the compliant side: count
 *   those five and the class is at 30, over the line.
 * - line 37, `WideInterface`; line 67, `WideTrait`; line 97, `WideEnum`; and
 *   line 129, an anonymous class — each holding 26 methods, one past the
 *   threshold. PHPMD's rule is ClassAware and pdepend reports nothing for an
 *   anonymous class, so none of these is PHPMD's subject. Registering T_CLASS
 *   alone reproduces that; register T_INTERFACE, T_TRAIT, T_ENUM, or
 *   T_ANON_CLASS beside it and this fixture reports four times.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * 26 counted methods — one past the maximum — earns exactly one error, on the
 * class declaration (line 3, column 1) rather than on any single method. The
 * defect is the size of the whole class, so it has no statement line of its
 * own.
 */
it('flags a class one method past the maximum on its declaration', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The message names the class and both numbers, so a report over a whole
 * application says which class to open and how far past the line it is.
 */
it('names the class and the counts in the message', function (): void {
    $errors = analyzeFixture(TOO_MANY_METHODS, 'failing.php')->getErrors();

    expect($errors[3][1][0]['message'])
        ->toContain('OneOverTheMaximum')
        ->toContain('26')
        ->toContain('25');
});

/**
 * PHPMD's shipped ignore pattern is a case-insensitive *prefix* match with no
 * word boundary, so it excludes far more than accessors. `prefix-matching.php`
 * declares 31 methods, six of which the pattern excludes — `isolate`, `hash`,
 * `within`, `withdraw` (prefixes, not whole words) and `GETdata`, `Setup`
 * (matching only because the trailing `i` folds case) — leaving exactly 25
 * counted, the compliant edge of the threshold.
 *
 * Sitting *on* the boundary is what makes silence discriminating down to a
 * single name: count any one of the six and the class reaches 26 and reports.
 * A fixture parked below the line would need two names to go wrong before
 * anything showed, so a regression that mishandled only `GETdata` would pass.
 *
 * Silence alone still cannot see the other direction — a pattern that grew to
 * swallow a `doThing*` name drops the count to 24 and stays just as quiet — so
 * the second half pins the number itself. Lowering `maxmethods` to 0 forces the
 * class to report and puts the count in the message, where a miscount either
 * way is visible as a number rather than inferred from a threshold.
 */
it('excludes case-insensitive prefix matches from the count', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'prefix-matching.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);

    $counted = analyzeFixture(
        TOO_MANY_METHODS,
        'prefix-matching.php',
        static function (object $sniff): void {
            $sniff->maxmethods = 0;
        }
    );

    expect(violationTuples($counted))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ])->and($counted->getErrors()[3][1][0]['message'])
        ->toContain('declares 25 counted methods');
});

/**
 * PHPMD counts the methods a class declares itself. `nested.php` puts
 * `OuterClass` at exactly 25 counted methods and then hides, inside one of
 * them, a plain function declaration and an anonymous class holding 26 more.
 * Both belong to their own scope.
 *
 * Because the outer class sits on the boundary, either scoping mistake reports
 * it: attribute the nested function to the class and it reaches 26, attribute
 * the anonymous class's methods and it reaches 51. The anonymous class itself
 * stays silent for the reason `passing.php` pins.
 */
it('counts only the methods the class declares itself', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'nested.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * `maxmethods` is a public sniff property, so a project that tolerates wider
 * classes can raise it. One fixture pins both directions: `Configured`'s 26
 * counted methods report under the shipped default and fall silent at 26. A
 * property that was ignored would leave both runs identical.
 */
it('exposes a configurable maximum', function (): void {
    expect(violationTuples(analyzeFixture(TOO_MANY_METHODS, 'configured.php')))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_ERROR],
    ]);

    $raised = analyzeFixture(
        TOO_MANY_METHODS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maxmethods = 26;
        }
    );

    expect($raised->getErrors())->toBe([]);
});

/**
 * `ignorepattern` is the other configurable half, and the same fixture pins it
 * both ways: `Configured`'s methods are all named `handleThing*`, which the
 * shipped pattern does not match, so the class reports until a ruleset points
 * the pattern at them.
 */
it('exposes a configurable ignore pattern', function (): void {
    $ignored = analyzeFixture(
        TOO_MANY_METHODS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignorepattern = '(^handle)i';
        }
    );

    expect($ignored->getErrors())->toBe([]);
});

/**
 * The pattern reaches the sniff from a consumer's XML, so it is the one input
 * here that can arrive malformed. Left to itself, `preg_match()` would return
 * false for every method, none would be excluded, and a typo would quietly
 * read as a *stricter* rule than the one configured.
 *
 * `broken-pattern.php` declares two methods, so it cannot exceed any
 * threshold: the single error it reports can only be the configuration one.
 * The fixture's second method is `getName`, which the shipped pattern would
 * exclude — proof the sniff stopped before counting rather than counting with
 * a filter that silently matched nothing.
 */
it('reports an unusable ignore pattern instead of miscounting', function (): void {
    $file = analyzeFixture(
        TOO_MANY_METHODS,
        'broken-pattern.php',
        static function (object $sniff): void {
            $sniff->ignorepattern = '(^(set|get';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 3, 'column' => 1, 'source' => TOO_MANY_METHODS_PATTERN_ERROR],
    ]);
});

/**
 * The compile failure is swallowed by the sniff's own error handler, so a
 * broken pattern does not also spray a raw "preg_match(): ..." warning across
 * PHPCS's report.
 *
 * The diagnostics are collected by a handler installed here rather than left
 * to PHPUnit's, which would only mark the test *warned* — and phpunit.xml.dist
 * sets no failOnWarning, so a warned test still passes. Recording them makes
 * the assertion the thing that fails: revert the sniff to `@preg_match()` and
 * this reddens, because PHP 8 routes suppressed diagnostics to the installed
 * handler all the same.
 */
it('emits no PHP warning while rejecting the pattern', function (): void {
    $diagnostics = [];

    set_error_handler(static function (int $errno, string $message) use (&$diagnostics): bool {
        $diagnostics[] = $message;

        return true;
    });

    try {
        analyzeFixture(
            TOO_MANY_METHODS,
            'broken-pattern.php',
            static function (object $sniff): void {
                $sniff->ignorepattern = '(^(set|get';
            }
        );
    } finally {
        restore_error_handler();
    }

    expect($diagnostics)->toBe([]);
});

/**
 * The valid-pattern path must leave the error handler exactly as it found it.
 * The `finally` that restores it is what this pins: drop it and the sniff's
 * silencing handler stays installed for the rest of the run.
 */
it('restores the error handler it installed', function (): void {
    $before = set_error_handler(null);
    restore_error_handler();

    analyzeFixture(TOO_MANY_METHODS, 'passing.php');

    $after = set_error_handler(null);
    restore_error_handler();

    expect($after)->toBe($before);
});

/**
 * Two shapes PHPCS hands a sniff mid-edit, neither of which it may fall over
 * on:
 *
 * - `truncated.php`, a class holding 26 counted methods whose body is never
 *   closed. The tokeniser leaves it without a scope, so there is no body to
 *   count and the sniff says nothing — the count it would report is not one
 *   the file supports yet.
 * - `nameless.php`, the `class` keyword with nothing after it. There is no
 *   name to report, which is what reaches the `$name === null` guard.
 *   Anonymous classes cannot reach it: PHPCS gives them their own
 *   T_ANON_CLASS token, which this sniff never registers for.
 */
it('passes over a half-written class', function (string $fixture): void {
    $file = analyzeFixture(TOO_MANY_METHODS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['truncated.php', 'nameless.php']);

/**
 * Pins the detection-only decision: splitting a class is a judgement about
 * where each behaviour belongs, so no violation is auto-fixable.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(TOO_MANY_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(1)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
