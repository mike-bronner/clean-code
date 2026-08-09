<?php

/**
 * Tests the custom CleanCode.Constructors.PrimaryConstructorDelegation sniff
 * (Constructors: Primary + Named Constructors, #34/#184). Fixtures live in
 * tests/fixtures/PrimaryConstructorDelegationSniff/.
 *
 * The sniff is an absence check: a named constructor — a `static` method
 * returning `self`, `static`, or the declaring class — whose body neither
 * instantiates that class nor hands off to another of its static methods earns
 * one warning on its `function` keyword. That shape makes a silent sniff
 * indistinguishable from a satisfied one, so every "no violations" assertion
 * here is paired with a fixture that does warn.
 *
 * The rule is detection-only: routing a body through the primary constructor
 * means deciding which parameter each local value feeds, so there is no
 * autofixed fixture.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */

declare(strict_types=1);

const DELEGATION = 'CleanCode.Constructors.PrimaryConstructorDelegation';

const DELEGATION_WARNING = DELEGATION . '.Missing';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DELEGATION);
});

/**
 * Every accepted delegation form stays silent, and so does everything the
 * sniff must not police.
 *
 * Most of what follows reddens this file on its own. Dropping the `new`
 * branch reports `fromCents()`, `fromDollars()` and `zero()` — the three
 * spellings that name the declaring class — along with the two written inside
 * a closure and an arrow function, both of which keep the enclosing class
 * binding. Dropping the static-call branch reports `fromString()`, which
 * delegates to another named constructor, and the two that go through
 * `hydrate()`, a helper declared `: object` and so not a named constructor
 * itself — the breadth #184 settled on. Dropping the `static` check reports
 * `withCents()`, an instance-level wither with no `new` in it. Treating an
 * enum as constructible reports `Status::fromLabel()`, which returns a case
 * because `new` on an enum is a fatal error. Dropping the bodiless guard
 * reports the abstract declaration and the interface signature.
 *
 * Three entries are boundaries this fixture documents rather than pins, each
 * pinned elsewhere or not pinnable at all:
 *
 * - `?self` on `tryFromString()` and `self|null` on `fromMixed()`. Reading a
 *   nullable spelling as "not a named constructor" leaves them uninspected,
 *   which is silence either way; the reporting side is what pins it, on
 *   failing.php lines 52 and 57.
 * - the plain `function make(): self` at file scope. It carries no conditions
 *   at all, so no owner check can find it a class name.
 * - the anonymous class's `new self()`. Its behaviour is pinned — the
 *   reporting half is failing.php line 74 — but the `$className !== null`
 *   guards beside it only keep `strtolower(null)` from deprecating and change
 *   no result, so no fixture can assert them.
 *
 * `currency(): string`, a static method returning something else, is the
 * remaining near miss: it is silent under every mutation above.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DELEGATION, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The six bypassing named constructors, each on its `function` keyword:
 * `unserialize()` (34), reflection (39), a deserializer's output returned raw
 * (47), the same `unserialize()` behind the two nullable return-type spellings,
 * `?self` (52) and `self|null` (57), and one on an **anonymous** class (74),
 * which has no name for the sniff to compare against — only `self`/`static`
 * can reach its primary constructor, and this method uses neither. The
 * compliant `fromAttributes()` on line 29 is not reported, so a sniff that
 * flagged every static method would not match this either.
 */
it('flags every named constructor that bypasses the primary constructor', function (): void {
    $file = analyzeFixture(DELEGATION, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            34 => [DELEGATION_WARNING],
            39 => [DELEGATION_WARNING],
            47 => [DELEGATION_WARNING],
            52 => [DELEGATION_WARNING],
            57 => [DELEGATION_WARNING],
            74 => [DELEGATION_WARNING],
        ]);
});

/**
 * The warning is attached to the `function` keyword, not to any statement
 * inside the body — the defect is the absence of delegation across the whole
 * method, so it has no line or column of its own. Column 19 is where
 * `function` starts under `    public static `.
 */
it('reports on the function keyword', function (): void {
    $tuples = warningTuples(analyzeFixture(DELEGATION, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 34, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 39, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 47, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 52, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 57, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 74, 'column' => 19, 'source' => DELEGATION_WARNING],
    ]);
});

/**
 * The message names the method, so a report over a whole codebase says which
 * one to open.
 */
it('names the method in the warning message', function (): void {
    $warnings = analyzeFixture(DELEGATION, 'failing.php')->getWarnings();

    expect($warnings[34][19][0]['message'])->toContain('fromSerialized()');
});

/**
 * Eight shapes that mention the declaring class without delegating to it, all
 * still reported. Each pins one condition of the detection, and a sniff that
 * loosened any of them would fall silent on that line:
 *
 * - line 36, `self::class` — a constant fetch, which is why an opening
 *   parenthesis has to follow the callee.
 * - line 41, `parent::open()` and line 46, `new parent()` — the superclass's
 *   constructor is a different one, so `parent` is not among the names that
 *   count.
 * - lines 51 and 56, `new \Other\Ticket()` and `\Other\Ticket::open()` — a
 *   namespaced name on each side of the detection. In a file declaring
 *   `Ticket` it names a different class far more often than this one, and a
 *   single-file scan cannot resolve which, so only an unqualified name counts.
 * - line 61, `self::open()` inside `open()` — recursion with no `new` anywhere
 *   in it never reaches a constructor, which is why the callee has to be a
 *   *different* method.
 * - line 66, `Ticket::REGISTRY` — a class constant, the own-name spelling of
 *   the line-36 case.
 * - line 78, `new self()` inside an anonymous class declared in the body.
 *   `self` there names the anonymous class, so the enclosing named constructor
 *   still bypasses its own primary constructor — the one nested scope the body
 *   scan jumps over rather than walking into.
 */
it('rejects shapes that only resemble delegation', function (): void {
    $file = analyzeFixture(DELEGATION, 'near-miss.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            36 => [DELEGATION_WARNING],
            41 => [DELEGATION_WARNING],
            46 => [DELEGATION_WARNING],
            51 => [DELEGATION_WARNING],
            56 => [DELEGATION_WARNING],
            61 => [DELEGATION_WARNING],
            66 => [DELEGATION_WARNING],
            78 => [DELEGATION_WARNING],
        ]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so the fixture ends on a `static`
 * method whose name has not been typed yet and never closes the class. With no
 * closing brace the tokenizer gives the class no scope, so no method carries
 * it as a condition and the sniff has no declaring class to reason about — not
 * even for the finished named constructor above. Passing over the file in
 * silence is the answer; naming a class it cannot see would be worse, and
 * PHPCS reports nothing else on an unparseable file either.
 *
 * The `$method === null` guard in the sniff reads as what handles this, but it
 * is defensive only and no fixture can pin it: PHPCS gives a closure its own
 * T_CLOSURE token, which this sniff never registers for, so the only nameless
 * T_FUNCTION is a truncated declaration — and a truncation deep enough to
 * strip the name also strips the class scope the guard above it needs. Stated
 * here because the code cannot say it.
 */
it('passes over a truncated file without falling over', function (): void {
    $file = analyzeFixture(DELEGATION, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Pins the detection-only decision, and the severity. Routing a body through
 * the primary constructor means deciding which parameter each local value
 * feeds, so no violation is auto-fixable; and a named constructor may
 * legitimately hand back a cached instance it did not build, so the standard
 * speaks as a warning rather than failing a consumer's build.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(DELEGATION, 'failing.php');

    expect($file->getWarningCount())->toBe(6)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
