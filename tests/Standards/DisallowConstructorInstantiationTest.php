<?php

/**
 * Tests the custom CleanCode.Classes.DisallowConstructorInstantiation sniff
 * (Dependency Injection, #72 — the partial-enforcement slice scoped on #176).
 * Fixtures live in tests/fixtures/DisallowConstructorInstantiationSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors:
 * "inject where possible" is a judgement about what the constructed class is,
 * and value objects, DTOs and default collaborators are legitimately built
 * inline. So there is no autofixed fixture, and the tests below prove every
 * reported violation is non-fixable — replacing an instantiation with an
 * injected parameter rewrites the class's signature and every call site, which
 * is not a mechanical fix.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 *
 * Each of the sniff's guards was mutation-checked against these fixtures, and
 * the result of each mutation is recorded here rather than assumed:
 *
 *   - drop the `__construct` name check — passing.php and shapes.php both
 *     redden (`Factory::digest()`, `__constructor()`, and the anonymous
 *     class's own `make()` start reporting)
 *   - drop the `throw new` skip — passing.php and shapes.php both redden
 *   - drop the nested-declaration skip — passing.php and shapes.php both
 *     redden (the closure, the arrow function, and the anonymous class's
 *     method start reporting)
 *   - scan from the `function` keyword instead of the body's opening brace —
 *     passing.php reddens on the new-in-initializers default parameter
 *
 * The bodyless-declaration guard is the one exception, and is stated plainly:
 * removing it changes no report — `$closer` becomes null, so the walk simply
 * never starts — and only raises a PHP undefined-key warning, which this
 * suite does not fail on. The interface constructor in shapes.php therefore
 * pins that the sniff stays silent and does not fall over on a declaration
 * with no body, matching how tests/Standards/RequireLazyLoadingPreventionTest
 * pins its own truncated-input case; it is not a red-on-mutation assertion.
 */

declare(strict_types=1);

const CONSTRUCTOR_INSTANTIATION = 'CleanCode.Classes.DisallowConstructorInstantiation';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CONSTRUCTOR_INSTANTIATION);
});

/**
 * passing.php carries the compliant form of the construct the sniff registers
 * on — constructors that receive their collaborators — plus every near-miss
 * shape the sniff must stay silent on: `throw new` in a constructor, `new` in
 * an ordinary method and a named constructor, `new` in the parameter list
 * (PHP 8.1 new-in-initializers), `new` inside a closure and an arrow function
 * declared in a constructor body, a `__constructor()` method whose name merely
 * resembles the real one, and an abstract constructor with no body at all.
 * Dropping any one of the sniff's guards reddens this test.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Both constructors in failing.php are reported, at the `new` keyword rather
 * than the assignment or the class name — line 41 proves a promoted parameter
 * in the same constructor does not suppress the warning for a collaborator
 * built in the body.
 */
it('warns once per instantiation at the new keyword', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 30, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 31, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 41, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(3);
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a constructor the sniff has no safe rewrite for. getFixableCount()
 * is used rather than violationFixableFlags(), which reads getErrors() only
 * and so would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'failing.php');

    expect($file->getWarningCount())->toBe(3)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a token walk that only ever handled a
 * `new` sitting at the top level of a plain class's constructor:
 *
 *   39 — a constructor declared in a trait
 *   49 — `__CONSTRUCT`, since PHP method names are case-insensitive
 *   63 — inside an `if` body: an ordinary constructor scope, still walked
 *   67 — inside a `foreach` body, same reason
 *   78 — three `new` tokens on one line, the outer call and both arguments
 *   88 — `new class {…}`: the anonymous class is instantiated here, so this
 *        one is reported; the `new Mailer()` inside its own method (line 91)
 *        is not, because a declaration scope nested in the body is jumped
 *
 * Two shapes in the file are deliberately absent from the list: the
 * `throw new RuntimeException` on line 104 (raising is not dependency
 * construction) and the bodyless interface constructor on line 113, which has
 * no scope to scan and must not crash the sniff.
 */
it('warns on every variant constructor and instantiation shape', function (): void {
    $file = analyzeFixture(CONSTRUCTOR_INSTANTIATION, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 39, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 49, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 63, 'column' => 29, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 67, 'column' => 31, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 36, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 78, 'column' => 50, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
        ['line' => 88, 'column' => 25, 'source' => CONSTRUCTOR_INSTANTIATION . '.Found'],
    ]);
});
