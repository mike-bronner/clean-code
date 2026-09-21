<?php

/**
 * Tests the custom CleanCode.Pattern.ThrowOnlyMethodOverride sniff (Pattern:
 * SOLID — Liskov Substitution, #5/#131). Fixtures live in
 * tests/fixtures/ThrowOnlyMethodOverrideSniff/.
 *
 * The sniff reports *refused bequest*: a method whose whole body is a single
 * `throw`, declared in a type that names a supertype. Three conditions have to
 * hold together, and a silent sniff looks the same whichever one failed, so
 * every "no violations" assertion here is paired with a fixture that does warn.
 *
 * The rule is detection-only — the remedy is splitting a hierarchy or
 * segregating an interface, neither of which is a mechanical rewrite — so
 * there is no autofixed fixture.
 *
 * CleanCode/ruleset.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const REFUSED_BEQUEST = 'CleanCode.Pattern.ThrowOnlyMethodOverride';

const REFUSED_BEQUEST_WARNING = REFUSED_BEQUEST . '.RefusedBequest';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(REFUSED_BEQUEST);
});

/**
 * Everything a hierarchy may legitimately carry, plus every near miss.
 *
 * All of it reddens this file on its own. Dropping the supertype check reports
 * six throw-only bodies that override nothing: `Storage::unsupported()` (38)
 * in a class naming no supertype, `Format::read()` (93) in a bare enum,
 * `Refusing::read()` (101) in a trait — which cannot declare a hierarchy at
 * all — `nestedRefusal()` (111) declared inside a method, the anonymous
 * class's `read()` (122) built without `implements`, and `freeRefusal()` (130)
 * at file scope.
 *
 * Asking the *outermost* enclosing scope instead of the innermost reports two
 * of those six — `nestedRefusal()` (111) and the anonymous class's `read()`
 * (122), both of which sit inside `Outer`, a class that does implement
 * `Reader`. That pair is what pins "innermost", which the other four cannot:
 * they have one enclosing scope or none.
 *
 * Dropping the `T_THROW` check on the body's first token reports
 * `orFail()` (78), whose `return throw ...` hands the caller a value rather
 * than refusing one, along with `FileStorage::read()` (46) and
 * `anonymous()` (119), two one-statement bodies that return normally.
 *
 * Dropping the check that the `throw` statement *ends* the body reports
 * `migrate()` (67), the one body that opens on `throw` and carries an
 * unreachable statement after it.
 *
 * Dropping the bodiless guard takes the suite down rather than reddening an
 * assertion: `NullStorage::read()` (86) and `SeekableReader::seek()` (31) have
 * no `scope_opener`, so the body reads dereference a missing array key. Both
 * sit in types that name a supertype, which is what gets them past the check
 * above and into those reads.
 *
 * Two shapes are boundaries this fixture documents rather than pins.
 * `seek()` (51) and `rewind()` (60) throw with other statements around them,
 * but each is caught by the first-token check before the end-of-body check is
 * reached, so `migrate()` is the only pin on the latter. And `nothing()` (74),
 * an empty body, is silent under every mutation above: dropping the
 * `$firstPtr === false` guard leaves it silent too, because PHP reads the
 * `false` key as `0` and finds the file's open tag there, which is not a
 * `throw`. That guard is correctness, not a coincidence to lean on, and no
 * fixture can assert it.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Nine stubbed-out methods, each on its `function` keyword. They cover every
 * way a supertype gets named and every spelling the body can take:
 *
 * - `extends` alone (33), `implements` alone (41 and 46), and both at once
 *   (54).
 * - An enum implementing an interface (94 and 99) and an anonymous class
 *   implementing one (115) — the two class-likes beside a named class that can
 *   declare a supertype.
 * - A body opening on a comment (54), which is not a statement.
 * - A re-thrown variable (72) rather than a `new` expression.
 * - A `throw` whose arguments carry a closure (80). Its body holds two
 *   semicolons of its own, so resolving the statement's end by scanning for
 *   the next `T_SEMICOLON` instead of asking PHPCS for it silences exactly
 *   this line — it is the only fixture that pins `findEndOfStatement()`.
 *
 * `Refusing::size()` (60), `RealStorage::read()` (107) and the anonymous
 * class's `size()` (120) are implemented for real and are not reported, so a
 * sniff that flagged every method of a subtype would not match this either.
 */
it('flags every method whose whole body is a throw', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            33 => [REFUSED_BEQUEST_WARNING],
            41 => [REFUSED_BEQUEST_WARNING],
            46 => [REFUSED_BEQUEST_WARNING],
            54 => [REFUSED_BEQUEST_WARNING],
            72 => [REFUSED_BEQUEST_WARNING],
            80 => [REFUSED_BEQUEST_WARNING],
            94 => [REFUSED_BEQUEST_WARNING],
            99 => [REFUSED_BEQUEST_WARNING],
            115 => [REFUSED_BEQUEST_WARNING],
        ]);
});

/**
 * The warning is attached to the `function` keyword, not to the `throw` inside
 * it: the defect is what the whole body *is*, so the declaration is the only
 * location that names the method rather than one line of it. Column 12 is
 * where `function` starts under `    public `; the anonymous class's method is
 * two levels deeper, at column 20.
 */
it('reports on the function keyword', function (): void {
    $tuples = warningTuples(analyzeFixture(REFUSED_BEQUEST, 'failing.php'));

    expect($tuples)->toBe([
        ['line' => 33, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 41, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 46, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 54, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 72, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 80, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 94, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 99, 'column' => 12, 'source' => REFUSED_BEQUEST_WARNING],
        ['line' => 115, 'column' => 20, 'source' => REFUSED_BEQUEST_WARNING],
    ]);
});

/**
 * The message has three jobs, and #131 asks for all three: name the method, so
 * a report over a whole codebase says which one to open; name the principle,
 * so the reader knows what the warning is about; and name the remedy — split
 * the hierarchy, or segregate the interface — rather than leaving "stub it out
 * differently" as the obvious reading.
 */
it('names the method, the principle, and the remedy', function (): void {
    $warnings = analyzeFixture(REFUSED_BEQUEST, 'failing.php')->getWarnings();

    expect($warnings[33][12][0]['message'])
        ->toContain('read()')
        ->toContain('Liskov Substitution')
        ->toContain('Split the hierarchy')
        ->toContain('segregate the interface');
});

/**
 * Warning severity is deliberate and is what #131 asks for. The heuristic
 * deliberately over-reports — a `private` stub, an abstract class's guarded
 * template method, and a method no supertype declares are all reported, per
 * the issue's "start strict" — so a report is a design prompt a reviewer may
 * dismiss, and must not fail a consumer's build.
 */
it('reports at warning severity, never as an error', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(9);
});

/**
 * Detection only: there is no mechanical rewrite of a refused bequest, so the
 * fixer must leave the violating fixture byte-identical. This is what keeps
 * the absent autofixed.php honest — a sniff that grew a fixer would redden
 * here rather than quietly shipping an unasserted one.
 */
it('offers no fix', function (): void {
    $file = analyzeFixture(REFUSED_BEQUEST, 'failing.php');

    expect($file->getFixableCount())->toBe(0)
        ->and(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath('ThrowOnlyMethodOverrideSniff', 'failing.php')));
});
