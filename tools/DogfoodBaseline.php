<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tools;

/**
 * The comparison behind the dogfood ratchet: does this tree carry more
 * rules.xml errors than the recorded baseline allows?
 *
 * Issue #229 asks that the package stop lint-ing consumer code to a standard
 * its own source does not meet. A gate demanding zero errors outright is not
 * reachable: measured on main at aa0f02e, `phpcs --standard=rules.xml
 * CleanCode/` reports 3052 errors across 105 of 107 files, and the bulk of them
 * (ArrayAccessors wanting `data_get()`, the complexity metrics against
 * inherently branch-heavy token walking) need either a Laravel helper this
 * package deliberately does not ship or a redesign of the sniffs themselves.
 *
 * So the gate ratchets instead of demanding zero. Every file's current error
 * count is pinned in tools/dogfood-baseline.json, and CI fails when any file
 * exceeds its pin. A file absent from the baseline is pinned at zero, so a new
 * sniff carrying errors fails the moment it lands — which is the regression
 * this issue exists to stop.
 *
 * The ratchet is deliberately tight in both directions. Cleaning a file without
 * re-recording the baseline also fails, because a baseline left above the real
 * count silently hands back the ground that cleanup just won: the next
 * regression up to the stale pin would pass. Failing loudly with "regenerate"
 * keeps every recorded number a measured one.
 *
 * Only errors are counted. Warnings do not gate `phpcs` and this repo's own
 * acceptance criteria are written against errors; `CleanCode.Conditionals.
 * AvoidConditionals` alone reports 1777 warnings on this tree, and admitting
 * them would be a different, much larger decision than the one #229 asks for.
 *
 * The logic here is pure — it takes a decoded phpcs JSON report and a recorded
 * baseline, and returns the differences. Nothing in this class runs phpcs or
 * touches the filesystem, so tests/Contract/DogfoodBaselineTest.php can drive
 * every branch from synthetic reports rather than from whatever the tree
 * happens to look like on the day.
 */
final class DogfoodBaseline
{
    /**
     * Reduce a decoded `phpcs --report=json` payload to relative-path =>
     * error-count, dropping every clean file.
     *
     * Clean files are dropped rather than recorded as zero so that the baseline
     * only ever lists debt: the file is a debt register that shrinks to nothing
     * as the tree is cleaned, and "absent means must be clean" is the same rule
     * for a brand-new sniff as for one that was cleaned last week.
     *
     * Paths are made relative to $root because the report carries absolute
     * ones, which differ between a contributor's checkout and the CI runner.
     * $root is resolved through realpath() first: on macOS /tmp is a symlink to
     * /private/tmp, and phpcs reports the resolved path, so comparing against
     * an unresolved root would match nothing and silently report a clean tree.
     *
     * @param array<string, mixed> $report Decoded phpcs JSON report.
     *
     * @return array<string, int> Sorted by path, so a regenerated baseline
     *                            diffs cleanly against the committed one.
     */
    public static function counts(array $report, string $root): array
    {
        $files = $report['files'] ?? null;

        if (!is_array($files)) {
            throw new \InvalidArgumentException(
                'phpcs report has no "files" key; the run probably failed before producing a report'
            );
        }

        $resolved = realpath($root);

        if ($resolved === false) {
            throw new \InvalidArgumentException("Cannot resolve repository root: {$root}");
        }

        $prefix = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $counts = [];

        foreach ($files as $path => $result) {
            $errors = is_array($result) ? ($result['errors'] ?? 0) : 0;

            if (!is_int($errors) || $errors <= 0) {
                continue;
            }

            $counts[self::relative((string) $path, $prefix)] = $errors;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Files carrying more errors than the baseline permits.
     *
     * A file the baseline does not mention is pinned at zero, so a newly added
     * file with any error at all is a regression.
     *
     * @param array<string, int> $baseline
     * @param array<string, int> $current
     *
     * @return array<string, array{baseline: int, current: int}> Sorted by path.
     */
    public static function regressions(array $baseline, array $current): array
    {
        $regressions = [];

        foreach ($current as $path => $errors) {
            $allowed = $baseline[$path] ?? 0;

            if ($errors > $allowed) {
                $regressions[$path] = ['baseline' => $allowed, 'current' => $errors];
            }
        }

        ksort($regressions);

        return $regressions;
    }

    /**
     * Files carrying fewer errors than the baseline records — cleanup that the
     * baseline has not been re-recorded to lock in.
     *
     * A file the current report does not mention is clean, so a baseline entry
     * with no counterpart is an improvement to zero rather than something to
     * skip. Iterating the baseline (not the report) is what makes those
     * visible: they appear in exactly one of the two sides.
     *
     * @param array<string, int> $baseline
     * @param array<string, int> $current
     *
     * @return array<string, array{baseline: int, current: int}> Sorted by path.
     */
    public static function improvements(array $baseline, array $current): array
    {
        $improvements = [];

        foreach ($baseline as $path => $allowed) {
            $errors = $current[$path] ?? 0;

            if ($errors < $allowed) {
                $improvements[$path] = ['baseline' => $allowed, 'current' => $errors];
            }
        }

        ksort($improvements);

        return $improvements;
    }

    /**
     * Strip $prefix from $path, leaving a repo-relative path with forward
     * slashes.
     *
     * A path outside the prefix is returned unchanged rather than mangled: it
     * means the caller scanned somewhere unexpected, and a wrong-looking path
     * in the baseline diff is a far better signal than a silently truncated one.
     */
    private static function relative(string $path, string $prefix): string
    {
        $relative = str_starts_with($path, $prefix)
            ? substr($path, strlen($prefix))
            : $path;

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
