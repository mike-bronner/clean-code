<?php

/**
 * Tests the custom CleanCode.Models.DisallowExternalPersistenceCalls sniff
 * (Models: Persistence Methods (Repository Pattern), #37). Fixtures live in
 * tests/fixtures/DisallowExternalPersistenceCallsSniff/: the blessed
 * $this-rooted persistence and the near-miss shapes the sniff must leave alone
 * in passing.php, the flagged calls in failing.php, and a pair of calls whose
 * verdicts swap with the configured method list in configured.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * CleanCode/ruleset.xml scopes the sniff out of test paths, and these fixtures live under
 * tests/ — so processing one in place reports nothing whatever the sniff does.
 * Every assertion about the sniff's own behaviour therefore runs against a copy
 * staged outside the repository ($stagedRun below), and the exclusion itself is
 * pinned separately by the scoped-out-of-test-paths test, which processes the
 * in-repo path and requires the silence to come from the path rather than from
 * the sniff having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const PERSISTENCE = 'CleanCode.Models.DisallowExternalPersistenceCalls';

const PERSISTENCE_WARNING = PERSISTENCE . '.Found';

// Fixtures are copied outside the repository before processing, because PHPCS
// decides CleanCode/ruleset.xml's test-path exclusion from the file's path alone. The
// staged copies are removed by the afterEach() hook in tests/Pest.php.
$stagedRun = static fn (string $fixture, ?callable $configure = null) => analyzeWithSniffs(
    [PERSISTENCE],
    stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', $fixture)),
    $configure
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PERSISTENCE);
});

/**
 * The blessed usage and every near-miss shape stay silent. Each group pins
 * one of the sniff's early returns, and a false positive on any of them
 * makes the rule unusable:
 *
 * - lines 3-6 and line 28, `$this->save()` and friends — the receiver
 *   check. This is the usage the standard mandates: a descriptive model
 *   method calling `$this->save()` at the end.
 * - lines 8-10, `User::create(...)`, `static::`, `parent::` — static calls
 *   carry no object operator at all, so the sniff never registers on them.
 *   Deliberately out of scope: `Model::create()` is token-indistinguishable
 *   from a named constructor or a factory API.
 * - lines 12-14, `saveQuietly()`, `updateOrFail()`, `persist()` — names
 *   that merely start with, extend, or paraphrase a configured name.
 * - lines 16-17, `$user->save` — a property read. Without the "next token
 *   is an open parenthesis" check these read as calls.
 * - lines 19-21, `$user->{$method}()`, `$user->{'save'}()` and
 *   `$user->$method()` — a dynamic member name is unknowable at token
 *   level. Every `->` reaches the sniff's T_STRING check; these are the
 *   three input shapes that the check actually *rejects*, and they cover
 *   both member tokens a dynamic name can produce: `{` for the two braced
 *   forms, T_VARIABLE for the plain variable one. They are silent either
 *   way — neither token's content is a legal PHP method name, so no
 *   configured name can equal it and removing the check changes no result
 *   here. The check is therefore a type guard rather than a behavioural
 *   branch, which is why no fixture can pin it; stated plainly rather than
 *   left to imply coverage.
 * - lines 31, 35 and 39, `create()`/`update()`/`delete()` method
 *   *declarations* — a model defining the very methods the sniff names
 *   must not flag itself.
 */
it('produces no violations on the compliant fixture', function () use ($stagedRun): void {
    $file = $stagedRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every external persistence call is flagged, once, at its own line:
 *
 * - lines 3-6, the four shipped names on a plain variable receiver.
 * - line 7, `$user?->save()` — the nullsafe operator is a separate token
 *   and has to be registered alongside the ordinary one.
 * - line 8, `$USER->SAVE()` — PHP method names are case-insensitive, so
 *   the comparison is too.
 * - lines 9-10, `User::query()->firstOrFail()->delete()` and
 *   `Order::factory()->create()` — a chained receiver ends in `)`, not a
 *   variable, and the intervening `firstOrFail()` is not a configured name,
 *   so each line earns exactly one warning rather than none or two.
 * - line 11, `$user->delete(...)` — first-class callable syntax still opens
 *   a parenthesis, so it reads as a call.
 * - line 12, `$this->agent->save()` — the receiver is a *property of*
 *   `$this`, a different object, so persistence on it is external. The
 *   guard has to test the token immediately before the operator; a check
 *   for `$this` anywhere in the statement would drop this line.
 * - lines 18 and 23, calls inside a controller — the shape the standard is
 *   actually aimed at.
 */
it('flags every violation at its own line with the expected code', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
            5 => [PERSISTENCE_WARNING],
            6 => [PERSISTENCE_WARNING],
            7 => [PERSISTENCE_WARNING],
            8 => [PERSISTENCE_WARNING],
            9 => [PERSISTENCE_WARNING],
            10 => [PERSISTENCE_WARNING],
            11 => [PERSISTENCE_WARNING],
            12 => [PERSISTENCE_WARNING],
            18 => [PERSISTENCE_WARNING],
            23 => [PERSISTENCE_WARNING],
        ]);
});

/**
 * The message names the offending method, so a developer reading the report
 * knows which call to move into the model. `$USER->SAVE()` is asserted
 * because the comparison is case-insensitive while the message is not: the
 * source spelling has to survive into the output rather than the lowercased
 * copy the check works from.
 */
it('names the offending method in the warning message', function () use ($stagedRun): void {
    $warnings = $stagedRun('failing.php')->getWarnings();

    expect($warnings[3][8][0]['message'])->toContain('save()')
        ->and($warnings[8][8][0]['message'])->toContain('SAVE()');
});

/**
 * The flagged names are a public sniff property, as the standard's doc
 * advertises. One fixture pins both directions with the same two lines:
 * under the shipped default `$user->save()` warns and
 * `$repository->persist()` does not, and once the list is replaced by
 * `persist` the verdicts swap. A property that was ignored would leave both
 * runs identical and fail the second assertion.
 */
it('exposes a configurable persistence-method list', function () use ($stagedRun): void {
    expect(array_keys($stagedRun('configured.php')->getWarnings()))->toBe([4]);

    $configured = $stagedRun(
        'configured.php',
        static function (object $sniff): void {
            $sniff->persistenceMethods = ['persist'];
        }
    );

    expect(array_keys($configured->getWarnings()))->toBe([3]);
});

/**
 * Factory chains (`User::factory()->create()`) make generic CRUD calls
 * idiomatic in test suites, so CleanCode/ruleset.xml scopes the sniff out of test
 * paths. The exclusion is a path match, so processing failing.php where it
 * actually lives — under tests/ — must report nothing, even though the same
 * bytes produce twelve warnings from outside the repository.
 *
 * Both halves are asserted together. The in-repo run alone would pass just
 * as well against a sniff that never fires at all, which is precisely the
 * failure mode the exclusion makes easy to ship unnoticed.
 */
it('is scoped out of test paths', function () use ($stagedRun): void {
    $inRepo = analyzeWithSniffs(
        [PERSISTENCE],
        fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php')
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($stagedRun('failing.php')->getWarnings())->toHaveCount(12);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so a chain can end at the
 * operator with no member after it at all (line 6). The sniff has to pass
 * over it rather than fall over or invent a diagnostic for it.
 *
 * The `$methodPtr === false` guard that reads as what prevents this is in
 * fact defensive only, and removing it changes no result: PHP resolves
 * `$tokens[false]` to `$tokens[0]`, the open tag, which fails the T_STRING
 * check on the next line anyway. It is kept for saying so outright instead
 * of leaning on that coercion. Stated here because no fixture can pin it —
 * this test covers the truncated chain, not the guard.
 *
 * Lines 3-4 keep the assertion honest — the file still has to report the
 * calls that precede the truncation, so a sniff that fell silent on the
 * whole file would fail here rather than pass. Line 4 also records that a
 * reserved word is a legal method name and is flagged like any other:
 * PHP_CodeSniffer re-labels a reserved word following `->` as T_STRING, so
 * `list` reaches the name comparison exactly as `save` does.
 */
it('handles a truncated chain without falling over', function () use ($stagedRun): void {
    $file = $stagedRun(
        'unterminated.php',
        static function (object $sniff): void {
            $sniff->persistenceMethods = ['save', 'list'];
        }
    );

    expect($file->getErrors())->toBe([])
        ->and(violationSourcesByLine($file->getWarnings()))->toBe([
            3 => [PERSISTENCE_WARNING],
            4 => [PERSISTENCE_WARNING],
        ]);
});

/**
 * Pins the detection-only decision: rewriting `$user->save()` into a
 * descriptive model method is a judgement about meaning, so no violation is
 * auto-fixable.
 */
it('reports detection-only warnings', function () use ($stagedRun): void {
    $file = $stagedRun('failing.php');

    expect($file->getWarningCount())->toBe(12)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The same verdict through the shipped, installed package.
 *
 * Every test above drives PHPCS in process through ConfigDouble, which supplies
 * the registration Composer would have supplied — so a package that never
 * registered itself with the installed standards passes all of them. This one
 * executes the real vendor/bin/phpcs as a separate process from outside the
 * package, against CleanCode/ruleset.xml, the file a consumer points --standard at. The
 * shared sweep in tests/Contract/ShippedPackageSmokeTest.php cannot reach this
 * sniff: it drives each fixture where it lives, under tests/, and this sniff's
 * <exclude-pattern> makes that path report nothing whatever the sniff does.
 *
 * Staged exactly as $stagedRun stages it, and asserted in the same paired shape
 * as the scoped-out-of-test-paths test above rather than only on the positive
 * half:
 *
 * - the staged copy reports all 12, every message under this sniff's own code,
 *   at status 1 — violations, none of them fixable, which is what this
 *   detection-only rule owes. Status 2 would mean phpcbf had been offered a
 *   fix, and 3 is what a broken install exits with.
 * - the in-repo copy of the same bytes reports nothing and exits 0, so the
 *   reporting half cannot be coming from a run that ignores CleanCode/ruleset.xml's
 *   exclusion.
 * - passing.php staged the same way reports nothing and exits 0 — the negative
 *   control, without which a shell-out that always reported would satisfy the
 *   first.
 */
it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('DisallowExternalPersistenceCallsSniff', 'failing.php');

    $staged = installedSniffRun(PERSISTENCE, stageFixtureOutsideTests($failing));
    $inRepo = installedSniffRun(PERSISTENCE, $failing);
    $passing = installedSniffRun(
        PERSISTENCE,
        stageFixtureOutsideTests(fixturePath('DisallowExternalPersistenceCallsSniff', 'passing.php'))
    );

    expect(array_column($staged['messages'], 'source'))->toHaveCount(12)
        ->each->toBe(PERSISTENCE_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
