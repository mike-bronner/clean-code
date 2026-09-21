<?php

/**
 * Tests the custom CleanCode.Testing.NoReflectionAccess sniff (Testing:
 * Guidelines, #54, partial enforcement per #145). Fixtures live in
 * tests/fixtures/NoReflectionAccessSniff/: the compliant public-API test plus
 * every near-miss shape in passing.php, the flagged Reflection access in
 * failing.php, the property-configurability pair in configured.php, the
 * per-line suppression in suppressed.php, and the two end-of-file truncations
 * in unterminated-member.php and unterminated-new.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * Unlike CleanCode.Models.DisallowExternalPersistenceCalls, this sniff is meant
 * to run *inside* test paths, so its fixtures are processed where they live —
 * tests/fixtures/ matches the shipped tests-directory glob. The reverse
 * direction, silence outside a test path, is pinned twice below: once by
 * retuning the property and once against a copy staged outside the repository.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const REFLECTION_ACCESS = 'CleanCode.Testing.NoReflectionAccess';

const REFLECTION_ACCESS_WARNING = REFLECTION_ACCESS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFLECTION_ACCESS);
});

/**
 * The blessed usage and every near-miss shape stay silent. Each group pins one
 * of the sniff's early returns, and a false positive on any of them makes the
 * rule unusable in a real suite:
 *
 * - lines 4-5, the compliant test — the class under test driven through its
 *   public API, which is what the standard asks for.
 * - lines 10-12, `new ReflectionClass()`, `new \ReflectionClass()` and
 *   `new ReflectionNamedType()` — classes deliberately absent from the shipped
 *   $reflectionClasses. Reading a class's metadata is legitimate; it becomes
 *   this standard's violation only once getMethod()/getProperty() narrows it
 *   to one member, which the member half catches instead. Line 11 also pins
 *   that the qualified-name walk does not over-fire: the same T_NS_SEPARATOR
 *   run that makes `new \ReflectionMethod` report must leave this one alone.
 * - lines 16-19, `getName()`, `getShortName()`, `getMethods()`,
 *   `getParentClass()` — Reflection members outside $reflectionMembers. None
 *   of them reaches a *named* non-public member.
 * - lines 22-24, `getMethodName()`, `invoker()`, `setAccessibleLabel()` —
 *   names that merely start with, extend, or paraphrase a configured name.
 * - lines 28-29, `$reflection->invoke` and `$reflection::setAccessible` — a
 *   property/constant read. Without the "next token is an open parenthesis"
 *   check these read as calls.
 * - lines 33-36, `$reflection->{$member}()`, `->{'getProperty'}()` and
 *   `->$member()` — a dynamic member name is unknowable at token level. These
 *   cover both member tokens a dynamic name can produce: `{` for the two
 *   braced forms, T_VARIABLE for the plain one. They are silent either way:
 *   neither token's content is a legal PHP method name, so no configured name
 *   can equal it, and deleting the sniff's `!== T_STRING` check changes no
 *   result here (verified by mutation — the suite stays green without it).
 *   That check is therefore a type guard rather than a behavioural branch,
 *   which is why no fixture can pin it; stated plainly rather than left to
 *   imply coverage.
 * - lines 39-40, `new ReflectionMethodFactory()` and `new MyReflectionProperty()`
 *   — class names that merely contain a configured one. The trailing-segment
 *   comparison must not degrade into a substring match.
 * - lines 44-57, a class *declaring* invoke()/setAccessible()/getMethod() — a
 *   declaration carries no `->`, `?->`, `::` or `new` before the name, so the
 *   sniff never registers on it. A class that happens to name its methods this
 *   way must not flag itself.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every Reflection access is flagged, once, at its own line. Grouped by the
 * detection that owns it.
 *
 * The instantiation half ($reflectionClasses):
 *
 * - lines 3-4, `new ReflectionMethod()` / `new ReflectionProperty()` — the two
 *   shipped classes, the AC's named shapes.
 * - line 5, `new \ReflectionMethod()` — PHP_CodeSniffer 3.x backfills PHP 8's
 *   single qualified-name token into T_NS_SEPARATOR + T_STRING, so reading one
 *   token after `new` yields `\` and matches nothing. This line is what forces
 *   the token-run walk; it reported nothing before that walk existed.
 * - line 6, `new REFLECTIONPROPERTY()` — PHP class names are case-insensitive,
 *   so the comparison is too.
 *
 * The member half ($reflectionMembers):
 *
 * - lines 7-8, `setAccessible(true)` and `setAccessible(false)`. The AC names
 *   the `true` spelling; the argument is deliberately not inspected, because
 *   `setAccessible(false)` is the same reach into the same member and PHP 8.1
 *   made the call a no-op either way — what the line signals is the Reflection
 *   route, not the flag.
 * - lines 9-10, `invoke()` / `invokeArgs()` on a reflection method object.
 * - lines 11-12, `getMethod()` / `getProperty()` on a ReflectionClass.
 * - line 13, `?->getMethod()` — the nullsafe operator is a separate token and
 *   has to be registered alongside the ordinary one.
 * - line 14, `ReflectionClass::getMethod()` — the AC's static spelling; `::`
 *   is registered so both spellings report.
 * - line 15, `(new ReflectionClass(...))->getProperty()` — the chained form.
 *   Exactly one warning: the instantiation is not itself a violation, and the
 *   receiver ending in `)` must not stop the member lookup.
 * - line 16, `GETMETHOD()` — method names are case-insensitive too.
 * - line 17, `$method->invoke(...)` — first-class callable syntax still opens
 *   a parenthesis, so it reads as a call.
 * - lines 23-24, the same access inside a real test method — the shape the
 *   standard is actually aimed at.
 */
it('flags every violation at its own line with the expected code', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [REFLECTION_ACCESS_WARNING],
            4 => [REFLECTION_ACCESS_WARNING],
            5 => [REFLECTION_ACCESS_WARNING],
            6 => [REFLECTION_ACCESS_WARNING],
            7 => [REFLECTION_ACCESS_WARNING],
            8 => [REFLECTION_ACCESS_WARNING],
            9 => [REFLECTION_ACCESS_WARNING],
            10 => [REFLECTION_ACCESS_WARNING],
            11 => [REFLECTION_ACCESS_WARNING],
            12 => [REFLECTION_ACCESS_WARNING],
            13 => [REFLECTION_ACCESS_WARNING],
            14 => [REFLECTION_ACCESS_WARNING],
            15 => [REFLECTION_ACCESS_WARNING],
            16 => [REFLECTION_ACCESS_WARNING],
            17 => [REFLECTION_ACCESS_WARNING],
            23 => [REFLECTION_ACCESS_WARNING],
            24 => [REFLECTION_ACCESS_WARNING],
        ]);
});

/**
 * Each warning is reported at the name itself, not at the operator or the
 * `new`, so an editor's inline marker sits under the offending token. The
 * columns are asserted for one line per detection shape rather than all
 * seventeen: the instantiation (line 3, the class name), the fully-qualified
 * instantiation (line 5, the leading `\` where the name starts), and the
 * chained member access (line 15, `getProperty` deep in the expression rather
 * than the `new` that precedes it on the same line).
 */
it('reports at the offending name rather than the operator', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect(warningTuples($file))
        ->toContain(['line' => 3, 'column' => 15, 'source' => REFLECTION_ACCESS_WARNING])
        ->toContain(['line' => 5, 'column' => 18, 'source' => REFLECTION_ACCESS_WARNING])
        ->toContain(['line' => 15, 'column' => 54, 'source' => REFLECTION_ACCESS_WARNING]);
});

/**
 * The message names the construct that triggered it and points at the standard
 * it enforces, so a developer reading the report knows both which call to
 * replace and why. `REFLECTIONPROPERTY` and `GETMETHOD` are asserted because
 * the comparison is case-insensitive while the message is not: the source
 * spelling has to survive into the output rather than the lowercased copy the
 * check works from. Line 5 pins that the *whole* written name reaches the
 * message, leading separator included, rather than just its trailing segment.
 */
it('names the construct and the standard in the warning message', function (): void {
    $warnings = analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings();

    expect($warnings[3][15][0]['message'])
        ->toContain('ReflectionMethod')
        ->toContain('test through the public API')
        ->toContain('docs/standards/testing-guidelines.md')
        ->and($warnings[5][18][0]['message'])->toContain('\\ReflectionMethod')
        ->and($warnings[6][14][0]['message'])->toContain('REFLECTIONPROPERTY')
        ->and($warnings[16][23][0]['message'])->toContain('GETMETHOD');
});

/**
 * Both flagged-name lists are public sniff properties, as the standard's doc
 * advertises. One fixture pins both directions for each: under the shipped
 * defaults `new ReflectionMethod()` (line 5) and `$reflection->getMethod()`
 * (line 10) warn while `new ReflectionClass()` (line 6) and
 * `$reflection->getName()` (line 11) do not, and once each list is replaced
 * the verdicts swap. A property that was ignored would leave the runs
 * identical and fail the second and third assertions.
 */
it('exposes configurable class and member lists', function (): void {
    expect(array_keys(analyzeFixture(REFLECTION_ACCESS, 'configured.php')->getWarnings()))
        ->toBe([5, 10]);

    $retunedClasses = analyzeFixture(
        REFLECTION_ACCESS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->reflectionClasses = ['ReflectionClass'];
            $sniff->reflectionMembers = [];
        }
    );

    expect(array_keys($retunedClasses->getWarnings()))->toBe([6]);

    $retunedMembers = analyzeFixture(
        REFLECTION_ACCESS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->reflectionClasses = [];
            $sniff->reflectionMembers = ['getName'];
        }
    );

    expect(array_keys($retunedMembers->getWarnings()))->toBe([11]);
});

/**
 * The test-file pattern is a public sniff property too, and it is what gates
 * the whole rule: retuning it to a glob the fixture's path cannot match must
 * silence a file that otherwise reports seventeen times.
 *
 * Both halves are asserted together. The retuned run alone would pass just as
 * well against a sniff that never fires at all, which is precisely the failure
 * mode a scoped rule makes easy to ship unnoticed.
 */
it('exposes a configurable test-file pattern that gates the whole rule', function (): void {
    $retuned = analyzeFixture(
        REFLECTION_ACCESS,
        'failing.php',
        static function (object $sniff): void {
            $sniff->testFilePatterns = ['*/production/*'];
        }
    );

    expect($retuned->getWarnings())->toBe([])
        ->and($retuned->getErrors())->toBe([])
        ->and(analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings())->toHaveCount(17);
});

/**
 * The same thing from the other side, with the shipped defaults untouched: the
 * identical bytes, at a path that is genuinely not a test path, report
 * nothing. The retuned-property test above proves the property is consulted;
 * this one proves the shipped patterns actually distinguish a real non-test
 * location, which a hand-picked glob cannot.
 *
 * The staged copy keeps the fixture's name (failing.php) and lands under the
 * system temp directory, so it matches neither shipped pattern. The staged
 * copies are removed by the afterEach() hook in tests/Pest.php.
 */
it('never inspects a file outside a test path', function (): void {
    $staged = analyzeWithSniffs(
        [REFLECTION_ACCESS],
        stageFixtureOutsideTests(fixturePath('NoReflectionAccessSniff', 'failing.php'))
    );

    expect($staged->getWarnings())->toBe([])
        ->and($staged->getErrors())->toBe([])
        ->and(analyzeFixture(REFLECTION_ACCESS, 'failing.php')->getWarnings())->toHaveCount(17);
});

/**
 * Legitimate Reflection in a test — framework plumbing, data-provider metadata
 * — takes the ordinary PHPCS per-line suppression rather than a sniff-specific
 * escape hatch. Lines 8 and 10 carry `phpcs:ignore` (on the preceding line and
 * trailing the statement respectively, the two placements PHPCS supports) and
 * report nothing; lines 15-16 carry the same constructs unsuppressed and still
 * report, so a sniff that had fallen silent on the whole file would fail here
 * rather than pass.
 */
it('honours per-line phpcs:ignore suppression', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'suppressed.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            15 => [REFLECTION_ACCESS_WARNING],
            16 => [REFLECTION_ACCESS_WARNING],
        ]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so a statement can end at `->` or
 * at `new` with nothing after it at all. Only at end-of-file is there
 * genuinely no following token, which is the only way to reach either `false`
 * guard — mid-file the walk finds a `;` and falls out on the type check
 * instead. The sniff has to pass over both rather than fall over or invent a
 * diagnostic.
 *
 * The two guards are not equally load-bearing, and the difference is recorded
 * rather than glossed over:
 *
 * - `new` at end-of-file (unterminated-new.php) genuinely needs its guard.
 *   Deleting it hands `false` to readName()'s `int $startPtr` under
 *   declare(strict_types=1), which is a TypeError, not a quiet miss — this
 *   test is what catches that.
 * - `->` at end-of-file (unterminated-member.php) does not. Deleting that
 *   guard changes no result: PHP resolves `$tokens[false]` to `$tokens[0]`,
 *   the open tag, which fails the following `!== T_STRING` check anyway. It is
 *   kept for saying so outright instead of leaning on that coercion, and this
 *   fixture covers the truncated chain rather than the guard.
 *
 * Each fixture still reports its two intact accesses, so a sniff that bailed
 * on the whole file would fail rather than pass.
 */
it('handles a truncated statement without falling over', function (string $fixture, array $lines): void {
    $file = analyzeFixture(REFLECTION_ACCESS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and(array_keys($file->getWarnings()))->toBe($lines);
})->with([
    ['unterminated-member.php', [6, 7]],
    ['unterminated-new.php', [4, 5]],
]);

/**
 * Pins the detection-only decision: replacing a Reflection call with coverage
 * through the public API is a redesign of the test, so no violation is
 * auto-fixable. Warnings rather than errors, because Testing: Guidelines is
 * advisory and the member half matches on name rather than receiver type — a
 * violation must not fail a consumer's build.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(REFLECTION_ACCESS, 'failing.php');

    expect($file->getWarningCount())->toBe(17)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
