<?php

/**
 * Integration test for the SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly
 * rule as configured in the master CleanCode/ruleset.xml, which replaces PHPMD's CleanCode
 * MissingImport rule (issue #84). Fixtures live in
 * tests/fixtures/ReferenceUsedNamesOnlySniff/.
 *
 * Every parity and divergence claim below was checked against a live PHPMD
 * 2.15.0 run over the same fixture files, with only MissingImport enabled.
 *
 * CleanCode/ruleset.xml sets two properties and leaves the rest at their vendor defaults.
 * Both halves of each property are pinned: the configured behaviour, and that
 * the fixture would report without it. A test that only asserted silence would
 * pass just as well if the property were dropped and the fixture were wrong.
 *
 * Beyond the contract's passing.php, failing.php and autofixed.php, three extra
 * fixtures carry shapes belonging to none of them:
 *
 * - no-namespace.php — the same defect in a file with no namespace, which the
 *   sniff reports under its own second code.
 * - divergences.php — where this ruleset is stricter than PHPMD, plus the shape
 *   neither tool catches. Described in docs/phpmd/cleancode-missingimport.md.
 * - throwable-interaction.php — the collision with the
 *   SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly rule wired in for
 *   issue #63, which rewrites a caught \Exception to a fully qualified
 *   \Throwable that this sniff then asks to be imported.
 *
 * Unlike the other PHPMD mappings in CleanCode/ruleset.xml, no <type> override is needed:
 * the sniff reports errors out of the box, so phpcs already fails a run the way
 * phpmd does. The error/warning split is asserted below so that stays a checked
 * fact rather than an assumption.
 */

declare(strict_types=1);

const REFERENCE_USED_NAMES_ONLY = 'SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly';

const REFERENCE_VIA_FQN = REFERENCE_USED_NAMES_ONLY . '.ReferenceViaFullyQualifiedName';

const REFERENCE_VIA_FQN_WITHOUT_NAMESPACE = REFERENCE_USED_NAMES_ONLY
    . '.ReferenceViaFullyQualifiedNameWithoutNamespace';

const THROWABLE_ONLY = 'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly';

/**
 * Every violation failing.php produces, in source order. PHPMD reports the
 * three `new` lines among them (13, 18, 29) and nothing else; the other five
 * are the documented breadth divergence, asserted here rather than described.
 */
const FAILING_VIOLATIONS = [
    ['line' => 9, 'column' => 9, 'source' => REFERENCE_VIA_FQN],    // use \App\...\Loggable trait
    ['line' => 11, 'column' => 29, 'source' => REFERENCE_VIA_FQN],  // \stdClass return type
    ['line' => 13, 'column' => 20, 'source' => REFERENCE_VIA_FQN],  // new \stdClass()          (PHPMD)
    ['line' => 16, 'column' => 32, 'source' => REFERENCE_VIA_FQN],  // \App\Models\Invoice type
    ['line' => 18, 'column' => 20, 'source' => REFERENCE_VIA_FQN],  // new \App\Models\Invoice() (PHPMD)
    ['line' => 23, 'column' => 16, 'source' => REFERENCE_VIA_FQN],  // \App\Models\Invoice::label()
    ['line' => 29, 'column' => 23, 'source' => REFERENCE_VIA_FQN],  // new \RuntimeException()   (PHPMD)
    ['line' => 30, 'column' => 18, 'source' => REFERENCE_VIA_FQN],  // catch (\Throwable)
];

/**
 * The line in failing.php that carries the fully qualified trait use, and its
 * compliant counterpart in passing.php. AC items 1 and 2 name class, interface
 * and trait; class is everywhere in these fixtures and interface rides on the
 * `catch (\Throwable)` line, so the trait needs its own named coverage.
 *
 * The sniff routes a class-body `use` through the same TYPE_CLASS path as `new`
 * (ReferencedNameHelper), which is why it works — but sharing a code path is
 * not sharing a test, and an upstream narrowing of that path would otherwise
 * pass this suite in silence.
 */
const TRAIT_USE_VIOLATION = ['line' => 9, 'column' => 9, 'source' => REFERENCE_VIA_FQN];

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFERENCE_USED_NAMES_ONLY);
});

/**
 * passing.php carries the near-miss shapes the sniff must stay silent on:
 * imported names in every position failing.php gets wrong — the trait use among
 * them — `self` and `static` (which PHPMD skips explicitly), a fallback global
 * function and constant, and the fully qualified global function and constant
 * CleanCode/ruleset.xml allows.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each fully qualified reference at its own line and column', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect(violationTuples($file))->toBe(FAILING_VIOLATIONS);
});

/**
 * The trait half of the class/interface/trait triad the AC names, pinned on its
 * own so the coverage survives a reshuffle of the map above.
 *
 * Both sides are asserted: the fully qualified `use \App\Billing\Support\Loggable;`
 * in failing.php is reported at its own line and column, and the imported
 * `use Loggable;` in passing.php is not — silence there is part of the whole
 * fixture's silence, but the trait line is the one this names.
 *
 * PHPMD 2.15.0 reports nothing on this line: a trait use is not an allocation
 * expression, so it joins the static call, the type hints and the catch clause
 * as breadth this ruleset adds. The remedy is the same one PHPMD asks for
 * elsewhere, so the report is kept.
 */
it('flags a fully qualified trait use and leaves an imported one alone', function (): void {
    $failing = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect(violationTuples($failing))->toContain(TRAIT_USE_VIOLATION);

    $traitLine = trim(explode("\n", (string) file_get_contents(
        fixturePath('ReferenceUsedNamesOnlySniff', 'failing.php')
    ))[TRAIT_USE_VIOLATION['line'] - 1]);

    expect($traitLine)->toBe('use \App\Billing\Support\Loggable;');

    $passing = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'passing.php');

    expect(violationTuples($passing))->toBe([]);
});

/**
 * And the fixer handles a trait use the same way it handles a `new`: the import
 * is added and the class-body reference is shortened. Asserted against the
 * fixer's own output on the trait line specifically, since the byte comparison
 * further down would also pass if autofixed.php were regenerated from a fixture
 * that had lost the trait case.
 */
it('imports a fully qualified trait use when it fixes the failing fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    $fixed = autofixedContents($file);

    expect($fixed)->toContain('use App\Billing\Support\Loggable;')
        ->and($fixed)->toContain('    use Loggable;')
        ->and($fixed)->not->toContain('use \App\Billing\Support\Loggable;');
});

/**
 * The sniff reports errors on its own account, which is why CleanCode/ruleset.xml carries
 * no <type> override for it — unlike Squiz.PHP.Eval and VariableAnalysis, whose
 * warnings have to be raised so phpcs fails the run the way phpmd does.
 */
it('reports fully qualified references as errors rather than warnings', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect($file->getErrorCount())->toBe(count(FAILING_VIOLATIONS))
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getWarnings())->toBe([]);
});

/**
 * PHPMD's `ignore-global` property maps onto allowFullyQualifiedGlobalClasses,
 * and both tools default it off — so `new \stdClass()` is a violation for both
 * out of the box. CleanCode/ruleset.xml leaves the default alone; this pins that the
 * mapping is real by driving the property from a test the way a consuming
 * ruleset would, and asserting the exact set that drops out.
 *
 * The four surviving reports are the namespaced trait and class, which
 * `ignore-global` never silenced in PHPMD either.
 */
it('silences global-namespace classes when ignore-global is switched on', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'failing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalClasses = true;
        }
    );

    expect(violationTuples($file))->toBe([
        TRAIT_USE_VIOLATION,
        ['line' => 16, 'column' => 32, 'source' => REFERENCE_VIA_FQN],
        ['line' => 18, 'column' => 20, 'source' => REFERENCE_VIA_FQN],
        ['line' => 23, 'column' => 16, 'source' => REFERENCE_VIA_FQN],
    ]);
});

/**
 * PHPMD flags `new \stdClass()` inside a function or method whether or not the
 * file declares a namespace, so the mapping has to reach namespace-less files
 * too. It does, under the sniff's second code — the remedy there is dropping
 * the leading backslash, since a use statement buys nothing in the global
 * namespace.
 *
 * allowWhenNoNamespace is what governs this, and its name reads backwards: its
 * *false* branch is an early return that skips such files entirely. CleanCode/ruleset.xml
 * therefore leaves it at the vendor default, true. The second assertion pins
 * that, so flipping the property to "match PHPMD" fails here instead of
 * silently dropping every namespace-less file from the rule.
 */
it('flags a fully qualified reference in a file with no namespace', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'no-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 29, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
        ['line' => 9, 'column' => 20, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
    ]);

    $skipped = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'no-namespace.php',
        static function (object $sniff): void {
            $sniff->allowWhenNoNamespace = false;
        }
    );

    expect($skipped->getErrors())->toBe([]);
});

/**
 * The one place PHPMD's `ignore-global` and its Slevomat counterpart part
 * company. PHPMD skips `new \stdClass()` in a namespace-less file once
 * ignore-global is on; the sniff still reports it, because the
 * without-a-namespace branch runs before the global-classes gate.
 *
 * CleanCode/ruleset.xml leaves both properties at the shared default, so nothing here is
 * live — this exists so the divergence stays a recorded fact if anyone ever
 * turns ignore-global on, and it is documented alongside the rest in
 * docs/phpmd/cleancode-missingimport.md.
 */
it('keeps reporting namespace-less files even with ignore-global switched on', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'no-namespace.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalClasses = true;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 29, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
        ['line' => 9, 'column' => 20, 'source' => REFERENCE_VIA_FQN_WITHOUT_NAMESPACE],
    ]);
});

/**
 * CleanCode/ruleset.xml allows fully qualified *global* functions and constants, because
 * MissingImport walks allocation expressions only and so has no opinion on
 * either, while the leading backslash on them is a deliberate idiom.
 *
 * passing.php's silence alone would not prove the properties do anything — a
 * fixture that tripped nothing looks identical. So each property is reverted to
 * its vendor default in turn, and the report it brings back is asserted
 * exactly. Drop either property from CleanCode/ruleset.xml and its half of this fails.
 */
it('allows fully qualified global functions and constants, and would report them without that', function (): void {
    $file = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalFunctions = false;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 42, 'column' => 16, 'source' => REFERENCE_VIA_FQN],    // \strlen()
    ]);

    $constants = analyzeFixture(
        REFERENCE_USED_NAMES_ONLY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->allowFullyQualifiedGlobalConstants = false;
        }
    );

    expect(violationTuples($constants))->toBe([
        ['line' => 42, 'column' => 51, 'source' => REFERENCE_VIA_FQN],    // \PHP_EOL
    ]);
});

/**
 * Pins the two shapes where this ruleset is stricter than PHPMD, and — by
 * asserting the whole map — the one shape neither tool reports.
 *
 * PHPMD 2.15.0 reports nothing at all on this fixture: a static call is not an
 * allocation expression, and the rule is MethodAware/FunctionAware, so a `new`
 * in top-level code is outside everything it visits. Both extra reports are
 * true instances of the defect the standard describes, so they are kept.
 *
 * The partially qualified `Models\Invoice` on lines 14 and 16 is absent from
 * both tools and stays code review: it would appear in this map if the sniff
 * caught it.
 */
it('flags the divergences from PHPMD exactly where they are recorded', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 16, 'source' => REFERENCE_VIA_FQN],    // static call
        ['line' => 20, 'column' => 17, 'source' => REFERENCE_VIA_FQN],    // new, outside any function
    ]);
});

/**
 * Unlike the other PHPMD mappings here, this rule is auto-fixable: the fixer
 * adds the missing use statement and shortens the reference in place. Both
 * halves are asserted — the exact output, and that every violation is offered
 * a fix rather than only some of them.
 */
it('offers a fix for every violation and rewrites the failing fixture', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'failing.php');

    expect($file->getFixableCount())->toBe(count(FAILING_VIOLATIONS))
        ->and(violationFixableFlags($file))->toBe(array_fill(0, count(FAILING_VIOLATIONS), true));

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ReferenceUsedNamesOnlySniff', 'autofixed.php')));
});

/**
 * The fixer is total for this rule: nothing is left over for a second pass.
 * Asserted against the fixer's own output rather than the fixable flags above,
 * which say only that a fix was offered.
 */
it('leaves no violation behind on its own fixed output', function (): void {
    $file = analyzeFixture(REFERENCE_USED_NAMES_ONLY, 'autofixed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The one live collision inside CleanCode/ruleset.xml. ReferenceThrowableOnly (#63)
 * rewrites a caught \Exception to the literal `\Throwable`, fully qualified —
 * which this sniff then reports. Leaving allowFullyQualifiedExceptions at its
 * default false is deliberate: switching it on would also silence
 * `new \RuntimeException()`, which PHPMD does report, and a missed report is a
 * reason to keep running phpmd.
 *
 * So both rules fire on the same reference, and the master ruleset is used
 * rather than the narrowed one because that overlap is the point.
 */
it('reports a caught general exception through both rules at once', function (): void {
    $file = analyzeWithMasterRuleset(
        fixturePath('ReferenceUsedNamesOnlySniff', 'throwable-interaction.php')
    );

    $sources = violationSourcesByLine($file->getErrors());

    expect($sources[13] ?? [])->toContain(THROWABLE_ONLY . '.ReferencedGeneralException')
        ->and($sources[13] ?? [])->toContain(REFERENCE_VIA_FQN);
});

/**
 * And they agree on a compliant form, which matters more than the double
 * report: `use Throwable;` plus an unqualified `catch (Throwable $exception)`
 * satisfies both, and one phpcbf pass over the whole ruleset reaches it. If the
 * two ever ping-ponged instead, this would not converge.
 */
it('converges on an imported Throwable when the whole ruleset is fixed', function (): void {
    $file = analyzeWithMasterRuleset(
        fixturePath('ReferenceUsedNamesOnlySniff', 'throwable-interaction.php')
    );

    $fixed = autofixedContents($file);

    expect($fixed)->toContain('use Throwable;')
        ->and($fixed)->toContain('catch (Throwable $exception)')
        ->and($fixed)->not->toContain('\Throwable')
        ->and($fixed)->not->toContain('\Exception');
});
