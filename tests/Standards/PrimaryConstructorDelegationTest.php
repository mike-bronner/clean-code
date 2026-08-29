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

use MikeBronner\CleanCode\Tests\PregFailure;

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
 * One more reddens it on its own: `fromRootInstance()` on line 72 delegates
 * with `new \Money(...)`, the root-qualified spelling of the class this file
 * declares. Reading a leading separator as a foreign class reports it.
 *
 * So do the two beside it, `fromOwnNameFactory()` on line 77 and
 * `fromRootNameFactory()` on line 82: the own-name and root-qualified
 * spellings delegating through `::` rather than `new`. They hold the segment
 * check honest from the other direction — rejecting a matched own name
 * whenever *any* token follows it, rather than only a namespace separator,
 * reports both.
 *
 * Three entries are boundaries this fixture documents rather than pins, each
 * pinned elsewhere or not pinnable at all:
 *
 * - `?self` on `tryFromString()`, `self|null` on `fromMixed()`, and `\Money`
 *   on `fromRoot()`. Reading any of those spellings as "not a named
 *   constructor" leaves the method uninspected, which is silence either way;
 *   the reporting side is what pins them, on failing.php lines 54, 59 and 64.
 * - the plain `function make(): self` at file scope. It carries no conditions
 *   at all, so no owner check can find it a class name.
 * - the anonymous class's `new self()`. Its behaviour is pinned — the
 *   reporting half is failing.php line 81 — but the `$className !== null`
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
 * The seven bypassing named constructors, each on its `function` keyword:
 * `unserialize()` (36), reflection (41), a deserializer's output returned raw
 * (49), the same `unserialize()` behind each of the three return-type
 * spellings that a narrower gate would miss — `?self` (54), `self|null` (59)
 * and the root-qualified `\Snapshot` (64) — and one on an **anonymous** class
 * (81), which has no name for the sniff to compare against, so only
 * `self`/`static` can reach its primary constructor and this method uses
 * neither. The compliant `fromAttributes()` on line 31 is not reported, so a
 * sniff that flagged every static method would not match this either.
 */
it('flags every named constructor that bypasses the primary constructor', function (): void {
    $file = analyzeFixture(DELEGATION, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            36 => [DELEGATION_WARNING],
            41 => [DELEGATION_WARNING],
            49 => [DELEGATION_WARNING],
            54 => [DELEGATION_WARNING],
            59 => [DELEGATION_WARNING],
            64 => [DELEGATION_WARNING],
            81 => [DELEGATION_WARNING],
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
        ['line' => 36, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 41, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 49, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 54, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 59, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 64, 'column' => 19, 'source' => DELEGATION_WARNING],
        ['line' => 81, 'column' => 19, 'source' => DELEGATION_WARNING],
    ]);
});

/**
 * The message names the method, so a report over a whole codebase says which
 * one to open.
 */
it('names the method in the warning message', function (): void {
    $warnings = analyzeFixture(DELEGATION, 'failing.php')->getWarnings();

    expect($warnings[36][19][0]['message'])->toContain('fromSerialized()');
});

/**
 * Twelve shapes that mention the declaring class without delegating to it, all
 * still reported. Each pins one condition of the detection, and a sniff that
 * loosened any of them would fall silent on that line:
 *
 * - line 46, `self::class` — a constant fetch, which is why an opening
 *   parenthesis has to follow the callee.
 * - line 51, `parent::open()` and line 56, `new parent()` — the superclass's
 *   constructor is a different one, so `parent` is not among the names that
 *   count.
 * - lines 61 and 66, `new \Other\Ticket()` and `\Other\Ticket::open()` — a
 *   name carrying a namespace segment, on each side of the detection. In a
 *   file declaring `Ticket` it names a different class far more often than
 *   this one, and a single-file scan cannot resolve which. The separator alone
 *   is not what disqualifies it: a root-qualified `\Ticket` in this same file
 *   would be the declaring class, as passing.php line 72 pins.
 * - lines 71 and 76, `new Ticket\Sub()` and `new \Ticket\Sub()` — the same
 *   qualified-name defect with this class's name in the *head* segment rather
 *   than the tail. Both build a class that merely lives under a namespace
 *   spelled like this one. These two are what pin the forward segment check:
 *   dropping it — checking only what precedes the match, never what follows
 *   it — reads both as delegation and silences exactly these two lines.
 *   `\Other\Ticket` above cannot pin it, because `Other` never matches
 *   `Ticket`, so the backward check is the only one that shape exercises.
 * - lines 81 and 86, `Ticket\Sub::open()` and `\Ticket\Sub::open()` — the `::`
 *   counterpart of the two above, and a no-regression pin rather than a second
 *   pin of the same check. They are reported for a different reason: the token
 *   before `::` is the qualified name's *tail* (`Sub`), which never matches
 *   `Ticket`, so head-segment collision cannot reach this side and both stay
 *   reported with the forward check dropped. They hold that reasoning still
 *   true against a future rewrite that resolved a qualified name from its head
 *   instead.
 * - line 91, `self::open()` inside `open()` — recursion with no `new` anywhere
 *   in it never reaches a constructor, which is why the callee has to be a
 *   *different* method.
 * - line 96, `Ticket::REGISTRY` — a class constant, the own-name spelling of
 *   the line-46 case.
 * - line 108, `new self()` inside an anonymous class declared in the body.
 *   `self` there names the anonymous class, so the enclosing named constructor
 *   still bypasses its own primary constructor — the one nested scope the body
 *   scan jumps over rather than walking into.
 */
it('rejects shapes that only resemble delegation', function (): void {
    $file = analyzeFixture(DELEGATION, 'near-miss.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            46 => [DELEGATION_WARNING],
            51 => [DELEGATION_WARNING],
            56 => [DELEGATION_WARNING],
            61 => [DELEGATION_WARNING],
            66 => [DELEGATION_WARNING],
            71 => [DELEGATION_WARNING],
            76 => [DELEGATION_WARNING],
            81 => [DELEGATION_WARNING],
            86 => [DELEGATION_WARNING],
            91 => [DELEGATION_WARNING],
            96 => [DELEGATION_WARNING],
            108 => [DELEGATION_WARNING],
        ]);
});

/**
 * A trait's static methods are inspected like a class's, and the one shape
 * inside a trait that no single-file sniff can reach is disclosed rather than
 * guessed at.
 *
 * `fromReflection()` on line 41 is the pin: a trait method typed `self` whose
 * body builds its instance with reflection is reported exactly as the same
 * body in a class would be. Dropping `T_TRAIT` from the constructible scopes
 * silences it, which is what makes trait support tested rather than assumed.
 * `zero()` (`new self`), `fromCents()` (`new static`) and `fromString()`
 * (a call to a sibling static method) stay silent beside it, so a sniff that
 * reported every static method in a trait would not match this file either.
 *
 * `fromSerialized(): Money` on line 48 is the disclosed blind spot, and the
 * only entry here the sniff passes over rather than clears. A trait is
 * compiled into whichever class uses it, and this file never says which class
 * that is, so a return type naming the eventual consumer matches neither
 * `self`/`static` nor the trait's own name and is not recognized as a named
 * constructor at all — however its body builds the instance. Its `unserialize()`
 * body is the same one reported on failing.php line 36, which is what shows
 * the silence comes from the return-type gate and nothing else.
 */
it('inspects trait-declared named constructors, and discloses the consumer-typed blind spot', function (): void {
    $file = analyzeFixture(DELEGATION, 'trait.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            41 => [DELEGATION_WARNING],
        ]);
});

/**
 * The root-qualified spelling of the declaring class's own name is that class
 * where the file declares no namespace, and a different one inside a
 * namespace. This fixture holds the second half; passing.php line 67/72 and
 * failing.php line 64 hold the first.
 *
 * Two lines pin it, one on each side of the detection, and both flip if a
 * leading separator is read as this class regardless of the namespace:
 *
 * - line 43, `fromGlobal()` — a named constructor of `App\Domain\Money` whose
 *   body builds the *global* `Money`, reaching no constructor of its own, so
 *   it is reported. Reading `new \Money()` as delegation silences it.
 * - line 48, `fromRoot(): \Money` — returns the global `Money`, so it is not a
 *   named constructor of this class and is never inspected, however its body
 *   builds the instance. Reading the return type as this class reports it.
 *
 * `fromCents()` and `fromSerialized()` hold the unqualified behaviour steady
 * beside them: a namespace changes nothing about a bare `self`, and the
 * reported one keeps the file from being vacuously silent.
 */
it('reads a root-qualified name as a different class inside a namespace', function (): void {
    $file = analyzeFixture(DELEGATION, 'namespaced.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            38 => [DELEGATION_WARNING],
            43 => [DELEGATION_WARNING],
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

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * returnsDeclaringClass() splits a union return type on `|` and `&`. A failed
 * split is false, and iterating a boolean is a TypeError that takes the run
 * down on the file. The guard falls back to the unsplit type, which is what a
 * type carrying no separator already reduces to — so `self` still answers
 * correctly and only a union goes unread.
 *
 * A union going unread is visible: the delegation warning at the union-returning
 * constructor disappears while every other warning in the fixture stays. That
 * is the guard's documented direction, and the empty diagnostics are what
 * separate it from an unguarded read reaching the same silence by warning its
 * way through a false.
 */
it('reads an unsplittable union return type as a single member', function (): void {
    $expected = allViolationSourcesByLine(analyzeFixture(DELEGATION, 'failing.php'));

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_split',
            static fn (): array => allViolationSourcesByLine(analyzeFixture(DELEGATION, 'failing.php')),
            static fn (string $pattern): bool => $pattern === '/[|&]/'
        );
    });

    expect(array_keys($expected))->toContain(59)
        ->and(array_keys($degraded))->not->toContain(59)
        ->and($diagnostics)->toBe([]);
});
