<?php

/**
 * Tests tools/DogfoodBaseline.php, the comparison behind the #229 dogfood
 * ratchet.
 *
 * The ratchet is what stops a new sniff quietly adding rules.xml errors to a
 * tree that already carries 3052 of them, so the property that matters is not
 * "it reports a difference" but "it reports the differences that must fail CI
 * and stays silent on the ones that must not". Every case below is therefore a
 * discriminating one: each pins a boundary where a plausible alternative
 * implementation would disagree, rather than a happy path that a stub
 * returning everything, or nothing, would also satisfy.
 *
 * The class is pure by design — it takes a decoded report and a recorded
 * baseline and returns arrays — so these drive synthetic reports instead of
 * running phpcs. A test that shelled out to phpcs would measure whatever the
 * tree happened to look like that day, and could not construct the equality
 * and absence cases at all.
 *
 * The end-to-end direction (phpcs really running, a really-regressing file
 * really failing) is covered by the CI step this issue adds, which runs the
 * same code against the real tree on every pull request.
 *
 * dogfoodReport() and dogfoodRoot() live in tests/Helpers.php with every other
 * helper these suites drive: PSR-1 will not have a test file both declare a
 * function and run the it() calls that are its point, and `composer lint`
 * enforces that over this tree.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tools\DogfoodBaseline;

/**
 * The count reduction keeps only files carrying errors, and makes their paths
 * relative to the repository root.
 *
 * Non-vacuous by mutation: dropping the `$errors <= 0` guard adds
 * Clean/Nothing.php at 0 and reddens this; dropping the relative() call leaves
 * absolute paths, which match no baseline key and would make every comparison
 * below silently pass on an empty intersection.
 */
it('keeps only files with errors, keyed by repository-relative path', function (): void {
    $root = dogfoodRoot();

    $counts = DogfoodBaseline::counts(
        dogfoodReport(['CleanCode/B.php' => 3, 'Clean/Nothing.php' => 0, 'CleanCode/A.php' => 1], $root),
        $root
    );

    expect($counts)->toBe(['CleanCode/A.php' => 1, 'CleanCode/B.php' => 3]);
});

/**
 * The result is sorted by path, so regenerating the baseline produces a diff
 * that reflects real changes rather than phpcs's traversal order.
 *
 * Non-vacuous by mutation: removing the ksort() leaves the insertion order
 * z/y/a, which is not the ascending order asserted here. toBe() compares key
 * order for arrays, which toEqual() would not.
 */
it('sorts counts by path so a regenerated baseline diffs cleanly', function (): void {
    $root = dogfoodRoot();

    $counts = DogfoodBaseline::counts(
        dogfoodReport(['CleanCode/z.php' => 1, 'CleanCode/y.php' => 1, 'CleanCode/a.php' => 1], $root),
        $root
    );

    expect(array_keys($counts))->toBe(['CleanCode/a.php', 'CleanCode/y.php', 'CleanCode/z.php']);
});

/**
 * The root is resolved through realpath() before paths are made relative to it.
 *
 * This is the macOS case that would otherwise disable the whole gate in
 * silence: /tmp is a symlink to /private/tmp, phpcs reports the resolved path,
 * and an unresolved prefix matches none of them. The counts would still be
 * non-empty — they would just be keyed by absolute path, so every baseline
 * lookup would miss and the ratchet would report a clean tree no matter what
 * the tree held.
 *
 * Non-vacuous by mutation: replacing realpath($root) with $root leaves the
 * absolute reported path in the key, and the expected relative key is absent.
 */
it('resolves a symlinked root so reported paths still come out relative', function (): void {
    $real = sys_get_temp_dir() . '/dogfood-real-' . bin2hex(random_bytes(6));
    $link = sys_get_temp_dir() . '/dogfood-link-' . bin2hex(random_bytes(6));

    mkdir($real);
    symlink($real, $link);

    try {
        $counts = DogfoodBaseline::counts(dogfoodReport(['CleanCode/A.php' => 2], (string) realpath($real)), $link);

        expect($counts)->toBe(['CleanCode/A.php' => 2]);
    } finally {
        unlink($link);
        rmdir($real);
    }
});

/**
 * A report that never got as far as listing files is an error, not an empty
 * result.
 *
 * This is the fail-closed case. phpcs exits non-zero whenever it reports
 * anything, so the CLI cannot use the exit status to tell "found violations"
 * from "died before producing a report" — a crashed run that decoded to
 * something without a files key would otherwise read as a spotless tree and
 * pass the gate.
 *
 * Non-vacuous by mutation: replacing the throw with `$files = []` returns an
 * empty array and no exception is raised.
 */
it('refuses a report with no files key rather than reading it as a clean tree', function (): void {
    expect(fn (): array => DogfoodBaseline::counts(['totals' => ['errors' => 0]], dogfoodRoot()))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * A root that does not resolve is an error too, for the same fail-closed
 * reason: realpath() returns false for a missing directory, and a false prefix
 * would leave every path absolute and every baseline lookup missing.
 *
 * Non-vacuous by mutation: dropping the `$resolved === false` check lets the
 * method continue and return a non-empty array instead of throwing.
 */
it('refuses a root that cannot be resolved', function (): void {
    expect(fn (): array => DogfoodBaseline::counts(
        dogfoodReport(['CleanCode/A.php' => 1], dogfoodRoot()),
        __DIR__ . '/does-not-exist-' . bin2hex(random_bytes(6))
    ))->toThrow(InvalidArgumentException::class);
});

/**
 * A file carrying more errors than its pin is a regression; one sitting exactly
 * on its pin is not.
 *
 * Both halves are in one test on purpose. The equality case is the boundary an
 * off-by-one lives on: `>=` instead of `>` fails every unchanged file in the
 * tree and makes the gate unusable, and no strictly-greater case can catch that.
 *
 * Non-vacuous by mutation: `>=` reddens this on Same.php; `<` or a bare `!==`
 * reddens it on Worse.php.
 */
it('reports a file over its pin and ignores one exactly on it', function (): void {
    $regressions = DogfoodBaseline::regressions(
        ['Worse.php' => 5, 'Same.php' => 5],
        ['Worse.php' => 6, 'Same.php' => 5]
    );

    expect($regressions)->toBe(['Worse.php' => ['baseline' => 5, 'current' => 6]]);
});

/**
 * A file the baseline does not mention is pinned at zero.
 *
 * This is the case the whole issue exists for: a newly added sniff that carries
 * rules.xml errors has no baseline entry, and must fail rather than be waved
 * through as "not tracked yet".
 *
 * Non-vacuous by mutation: defaulting the missing entry to PHP_INT_MAX, or
 * skipping paths absent from the baseline, returns an empty array here.
 */
it('pins a file absent from the baseline at zero errors', function (): void {
    expect(DogfoodBaseline::regressions([], ['CleanCode/New.php' => 1]))
        ->toBe(['CleanCode/New.php' => ['baseline' => 0, 'current' => 1]]);
});

/**
 * Cleanup that the baseline has not been re-recorded to lock in is reported.
 *
 * A baseline left above the real count silently hands the ground back: a later
 * regression up to that stale pin would pass. So an improvement has to fail the
 * gate too, with a message asking for a regenerate.
 *
 * Non-vacuous by mutation: `<=` also reports Same.php, which is asserted absent
 * here; dropping the branch returns an empty array.
 */
it('reports a file cleaner than its pin and ignores one exactly on it', function (): void {
    $improvements = DogfoodBaseline::improvements(
        ['Better.php' => 5, 'Same.php' => 5],
        ['Better.php' => 2, 'Same.php' => 5]
    );

    expect($improvements)->toBe(['Better.php' => ['baseline' => 5, 'current' => 2]]);
});

/**
 * A baselined file that has become completely clean is an improvement to zero,
 * not an entry to skip.
 *
 * It appears in the baseline and not in the report, so it is reachable only by
 * iterating the baseline. Iterating the report instead — the obvious symmetry
 * with regressions() — silently drops exactly the files whose debt was fully
 * paid off, which is the one improvement most worth recording.
 *
 * Non-vacuous by mutation: iterating $current rather than $baseline returns an
 * empty array here.
 */
it('treats a baselined file missing from the report as cleaned to zero', function (): void {
    expect(DogfoodBaseline::improvements(['Fixed.php' => 4], []))
        ->toBe(['Fixed.php' => ['baseline' => 4, 'current' => 0]]);
});

/**
 * The two directions are mutually exclusive on any one file, so a mixed tree
 * routes each file to exactly one list.
 *
 * Non-vacuous by mutation: a shared comparison that reported any inequality in
 * both lists would put Worse.php into improvements and Better.php into
 * regressions, and both assertions below would fail.
 */
it('routes a worsened and an improved file to separate lists', function (): void {
    $baseline = ['Worse.php' => 1, 'Better.php' => 9];
    $current = ['Worse.php' => 2, 'Better.php' => 3];

    expect(array_keys(DogfoodBaseline::regressions($baseline, $current)))->toBe(['Worse.php'])
        ->and(array_keys(DogfoodBaseline::improvements($baseline, $current)))->toBe(['Better.php']);
});
