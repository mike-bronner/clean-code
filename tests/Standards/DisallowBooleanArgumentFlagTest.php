<?php

/**
 * Tests the custom CleanCode.Functions.DisallowBooleanArgumentFlag sniff
 * (PHPMD CleanCode BooleanArgumentFlag, #76). Fixtures live in
 * tests/fixtures/DisallowBooleanArgumentFlagSniff/, and the mapping is
 * documented in docs/phpmd/cleancode-booleanargumentflag.md.
 *
 * Every expectation below was cross-checked against a live PHPMD 2.15.0 run
 * over the same fixture files, because the point of the sniff is that `phpmd`
 * no longer has to run for this rule. PHPMD reports failing.php lines 7, 11,
 * 15, 35, and 63 — the five parameters carrying a boolean *default* — and
 * stays silent on passing.php, divergences.php, and every other line of
 * failing.php. This sniff reports all five plus the type-declaration shapes
 * #76 adds, so its output is a strict superset: no PHPMD finding is lost.
 *
 * The rule is detection-only. Removing a flag argument splits the callee in
 * two and rewrites its call sites, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const BOOLEAN_ARGUMENT_FLAG = 'CleanCode.Functions.DisallowBooleanArgumentFlag';

const BOOLEAN_ARGUMENT_FLAG_ERROR = BOOLEAN_ARGUMENT_FLAG . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_ARGUMENT_FLAG);
});

/**
 * The compliant fixture carries the constructs the sniff registers on —
 * methods, a plain function, a closure, an arrow function, an interface
 * method, an enum method — plus the near-miss shapes it must stay silent on:
 *
 * - line 9, a `bool` *property* initialised to `true`, and line 7 a `true`
 *   class constant. Neither is a parameter.
 * - line 15, non-boolean parameters with non-boolean defaults.
 * - line 20, a `bool` *return* type.
 * - line 25, the union `bool|string`. That parameter carries a value, not a
 *   branch selector, so widening the type check to "mentions bool" would
 *   report it.
 * - line 30, the variadic `bool ...$flags` — a list of booleans, and PHP
 *   forbids a default on a variadic, so PHPMD cannot report one either.
 * - line 35, a default of `self::VERBOSE`, a constant whose value *is* `true`,
 *   and line 40 a default of `!true`. PHPCS hands over the default expression
 *   verbatim and PDepend resolves neither, so both tools stay silent.
 * - line 45, a `null` default, and line 51 a parameter typed `bool` in a
 *   docblock only — an annotation is not a native type declaration.
 * - line 65, `var_export(true, true)` and the surrounding calls: `true` passed
 *   as an *argument* is not a parameter declaration.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every flagged shape, reported on its own parameter rather than on the
 * declaration, so a signature with two flags earns two diagnostics (line 39).
 *
 * - line 7, a promoted constructor property with a `false` default.
 * - lines 11 and 15, an untyped parameter defaulted to `true` and to `FALSE`
 *   — the casing is irrelevant, as it is to PHPMD.
 * - lines 19, 23, 27, and 31, the four spellings that resolve to a boolean
 *   type declaration: `bool`, `?bool`, `bool|null`, `null|bool`.
 * - line 35, a static method whose *second* parameter is the flag, so the
 *   scan cannot stop at the first parameter.
 * - line 39, two flags in one signature.
 * - lines 45 and 47, a closure and an arrow function.
 * - lines 55 and 60, an interface method and an abstract method — neither has
 *   a body to scan, and neither needs one.
 * - line 63, a plain function outside any class.
 */
it('flags every boolean flag argument in the failing fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 46, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 11, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 15, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 19, 'column' => 34, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 23, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 27, 'column' => 37, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 31, 'column' => 39, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 35, 'column' => 53, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 39, 'column' => 35, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 39, 'column' => 46, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 45, 'column' => 36, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 47, 'column' => 27, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 55, 'column' => 34, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 60, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 63, 'column' => 20, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

/**
 * The message names the declaration and the offending parameter, so a report
 * over a whole codebase says which signature to open. `render()` is a method
 * of a class, which is why the subject reads "method" rather than "function".
 */
it('names the declaration and the parameter in the message', function (): void {
    $errors = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors();

    expect($errors[11][28][0]['message'])
        ->toContain('method render()')
        ->toContain('$withHeader');
});

/**
 * The two shapes where this ruleset reports and PHPMD does not, both confirmed
 * silent under a live `phpmd ... cleancode` run over this very file:
 *
 * - line 7, `bool $silent` with no default. PHPMD only ever inspects a
 *   parameter's resolved default value, so a type declaration alone never
 *   reaches its check. #76 requires this half, and rules.xml requires a native
 *   type hint on every parameter, so it is the shape that actually occurs here.
 * - line 13, a closure at file scope. PHPMD's rule visits method and function
 *   nodes and searches their subtrees, so it sees a closure nested in one but
 *   never a closure that is nested in nothing.
 *
 * Both extra reports are true defects, so they are kept — the same call
 * rules.xml records for VariableAnalysis. Neither direction loses a PHPMD
 * finding, which is what keeps `phpmd` out of the pipeline for this rule.
 */
it('reports the two shapes PHPMD misses', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 31, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 13, 'column' => 24, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

/**
 * Both properties ship empty, exactly as PHPMD's cleancode.xml ships them:
 * out of the box nothing is exempt, constructors included. This run is the
 * baseline the two property tests below are measured against — without it,
 * a property that silenced the sniff outright would look like a working
 * exemption.
 */
it('exempts nothing by default', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'configured.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 38, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 9, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 16, 'column' => 45, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 21, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

/**
 * `exceptions` names whole classes, matched against the unqualified name of
 * the enclosing class — PDepend's `getName()`, which is what PHPMD compares
 * against, drops the namespace.
 *
 * Naming `ExemptedRenderer` silences its three methods (lines 7, 16, 21) and
 * the closure nested in its constructor (line 9), and leaves two reports:
 *
 * - line 37, `exemptedHelper()`, a function at file scope with no enclosing
 *   class at all.
 * - line 29, `toggle()` inside the *anonymous* class returned by
 *   `factory()`. The search for an enclosing class stops at the anonymous
 *   class, which has no name to list, rather than walking on to
 *   `ExemptedRenderer` and inheriting its exemption. Dropping T_ANON_CLASS
 *   from the sniff's class-like token list removes this line.
 */
it('exempts the classes named by the exceptions property', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ExemptedRenderer';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

/**
 * PHPMD matches its exception list case-sensitively — `array_flip()` on the
 * configured names, keyed by the class name as written — and so does this
 * sniff, verified against PHPMD 2.15.0 with a lower-cased entry. A looser
 * match would exempt code PHPMD still reports, which is the one direction
 * that puts `phpmd` back in the pipeline.
 *
 * The sibling CleanCode.Models.RequireLazyLoadingPrevention sniff matches its
 * own class list case-insensitively on purpose; that list answers to no
 * external tool. This one does, so the two differ deliberately.
 */
it('matches the exceptions list case-sensitively', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'exemptedrenderer';
        }
    );

    expect(violationTuples($file))->toHaveCount(6);
});

/**
 * `ignorepattern` is a PCRE matched against a declaration's own name. PHPMD's
 * documented example is used verbatim, and it exempts `__construct` (line 7)
 * and `getResultsFiltered` (line 16) while `render` (line 21) is untouched —
 * so the pattern is genuinely applied rather than the sniff falling silent.
 *
 * Line 9 is the third divergence from PHPMD: the closure declared inside the
 * exempted constructor keeps its report. PHPMD tests the pattern against the
 * *enclosing* method's name, so it drops that closure — confirmed against a
 * live PHPMD 2.15.0 run over this fixture with the same pattern. A closure has
 * its own signature and its own responsibility, so exempting a method by name
 * does not exempt the callables written inside it.
 */
it('exempts the names matched by the ignore pattern', function (): void {
    $file = analyzeFixture(
        BOOLEAN_ARGUMENT_FLAG,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignorepattern = '/^(__construct|get.*Filtered)$/';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 9, 'column' => 28, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 21, 'column' => 33, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 29, 'column' => 41, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
        ['line' => 37, 'column' => 30, 'source' => BOOLEAN_ARGUMENT_FLAG_ERROR],
    ]);
});

/**
 * A malformed pattern exempts nothing rather than everything. `preg_match()`
 * answers `false` on a pattern it cannot compile, and the sniff compares
 * strictly against `1`, so a configuration mistake over-reports instead of
 * silently switching the rule off. Loosening that comparison to `!== 0` turns
 * every named declaration in this fixture green and leaves only the closure
 * and the anonymous class's method behind.
 *
 * The failure is forced the only way PHP offers — an uncompilable pattern —
 * and the emitted warning is captured so the run stays quiet *and* so the
 * assertion proves the pattern really did fail to compile rather than merely
 * failing to match.
 */
it('reports everything when the ignore pattern is malformed', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    }, E_WARNING);

    try {
        $file = analyzeFixture(
            BOOLEAN_ARGUMENT_FLAG,
            'configured.php',
            static function (object $sniff): void {
                $sniff->ignorepattern = 'not-a-pattern';
            }
        );
    } finally {
        restore_error_handler();
    }

    expect($raised)->not->toBeEmpty()
        ->and($raised[0])->toContain('Delimiter must not be alphanumeric')
        ->and(violationTuples($file))->toHaveCount(6);
});

/**
 * Pins the detection-only decision: splitting a callee in two and rewriting
 * its call sites is a design change, not a formatting fix, so no violation is
 * offered to the fixer. PHPMD has no fix for this rule either.
 *
 * The severity is error, matching PHPMD, where a BooleanArgumentFlag
 * violation fails the run. Reported as a warning, `phpcs` would exit 0 and
 * `phpmd` would still have to run for this rule.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php');

    expect($file->getErrorCount())->toBe(15)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * isBooleanType() normalises a hint before deciding whether it is `bool`. A
 * failed read cast to a string is '', which resolves to no members and reads
 * exactly like a hint that is not boolean — the failure would silently exempt
 * the parameter from the check. The guard falls back to the written hint, which
 * resolves identically for every hint PHPCS hands over from a native
 * declaration, so every flag argument stays flagged.
 */
it('reads a type hint that cannot be normalised as written', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => violationSourcesByLine(analyzeFixture(BOOLEAN_ARGUMENT_FLAG, 'failing.php')->getErrors()),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
