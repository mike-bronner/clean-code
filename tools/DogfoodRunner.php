<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tools;

/**
 * The dogfood ratchet's command line, behind a class so that tools/dogfood.php
 * can be a file that only executes (#229).
 *
 * PSR-1 does not allow one file to both declare symbols and cause side
 * effects, and `composer lint` enforces that over this tree — the same reason
 * the Pest helpers live in tests/Helpers.php rather than in the test files that
 * call them. So the entry point declares nothing and this class holds the
 * whole of the behaviour.
 *
 * What it does: run phpcs over the sniff sources, reduce the report to per-file
 * error counts, and compare those to tools/dogfood-baseline.json. The
 * comparison itself is DogfoodBaseline's, kept separate because it is pure and
 * therefore testable without running phpcs at all.
 */
final class DogfoodRunner
{
    /**
     * The tree the ratchet guards, and the standard it is measured against.
     *
     * Fixtures are excluded because they are deliberately non-conforming input:
     * every sniff's failing fixture exists precisely to violate a rule, so
     * linting them would pin thousands of intentional violations and leave the
     * baseline measuring nothing anyone can act on.
     */
    private const TARGET = 'CleanCode';
    private const STANDARD = 'rules.xml';
    private const IGNORE = '*/fixtures/*';

    /**
     * Raised above the 128M default because tokenizing this tree needs more:
     * measured at 142M peak, and phpcs past the limit dies with a fatal error
     * mid-run instead of producing a report.
     */
    private const MEMORY_LIMIT = '512M';

    public static function run(string $root, bool $generate): int
    {
        $counts = DogfoodBaseline::counts(self::report($root), $root);

        if ($generate) {
            self::write($root, $counts);

            printf(
                "Recorded %d errors across %d files in %s\n",
                array_sum($counts),
                count($counts),
                self::baselinePath($root)
            );

            return 0;
        }

        return self::check($root, $counts);
    }

    /**
     * @param array<string, int> $counts
     */
    private static function check(string $root, array $counts): int
    {
        $recorded = self::read($root);
        $regressions = DogfoodBaseline::regressions($recorded, $counts);
        $improvements = DogfoodBaseline::improvements($recorded, $counts);

        if ($regressions !== []) {
            self::render('New rules.xml errors against the recorded baseline:', $regressions);
            echo "Fix them, or — if they are genuinely unavoidable — say so in review and\n"
                . "re-record with `composer dogfood -- --generate`.\n";
        }

        if ($improvements !== []) {
            self::render('Files now cleaner than the baseline records:', $improvements);
            echo "Lock the win in with `composer dogfood -- --generate` and commit the baseline.\n";
        }

        if ($regressions !== [] || $improvements !== []) {
            return 1;
        }

        printf("Dogfood baseline matches: %d errors across %d files.\n", array_sum($counts), count($counts));

        return 0;
    }

    /**
     * Run phpcs over the guarded tree and return the decoded JSON report.
     *
     * phpcs exits non-zero whenever it reports anything, which is the normal
     * case here, so the exit status is deliberately not read as failure. What
     * is checked instead is that the output decodes to a report at all: that
     * separates "ran and found violations" from "died before producing one",
     * which the exit status cannot, and which would otherwise read as a clean
     * tree and pass the gate.
     *
     * @return array<string, mixed>
     */
    private static function report(string $root): array
    {
        $phpcs = $root . '/vendor/bin/phpcs';

        if (!is_file($phpcs)) {
            self::fail("Cannot find {$phpcs} — run `composer install` first.");
        }

        $command = sprintf(
            '%s %s -d memory_limit=%s --standard=%s %s --ignore=%s --report=json',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phpcs),
            escapeshellarg(self::MEMORY_LIMIT),
            escapeshellarg($root . '/' . self::STANDARD),
            escapeshellarg($root . '/' . self::TARGET),
            escapeshellarg(self::IGNORE)
        );

        $output = shell_exec($command);

        if (!is_string($output)) {
            self::fail('phpcs produced no output.');
        }

        $decoded = json_decode((string) $output, true);

        if (!is_array($decoded)) {
            self::fail("phpcs did not produce a JSON report:\n" . substr((string) $output, 0, 2000));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, int>
     */
    private static function read(string $root): array
    {
        $path = self::baselinePath($root);

        if (!is_file($path)) {
            self::fail("No baseline at {$path} — run `composer dogfood -- --generate`.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded) || !isset($decoded['files']) || !is_array($decoded['files'])) {
            self::fail("Baseline at {$path} is not a readable baseline file.");
        }

        /** @var array<string, int> $files */
        $files = $decoded['files'];

        return $files;
    }

    /**
     * @param array<string, int> $counts
     */
    private static function write(string $root, array $counts): void
    {
        $payload = [
            '_comment' => 'Generated by `composer dogfood -- --generate` (#229). Per-file rules.xml '
                . 'ERROR counts for CleanCode/. CI fails when a file exceeds its entry, and when a '
                . 'file beats it without this file being re-recorded. A path absent from "files" '
                . 'must report zero errors.',
            'total' => array_sum($counts),
            'files' => $counts,
        ];

        file_put_contents(
            self::baselinePath($root),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * @param array<string, array{baseline: int, current: int}> $rows
     */
    private static function render(string $heading, array $rows): void
    {
        echo $heading . "\n";

        foreach ($rows as $path => $row) {
            printf("  %-72s %d -> %d\n", $path, $row['baseline'], $row['current']);
        }

        echo "\n";
    }

    private static function baselinePath(string $root): string
    {
        return $root . '/tools/dogfood-baseline.json';
    }

    /**
     * @return never
     */
    private static function fail(string $message)
    {
        fwrite(STDERR, $message . "\n");

        exit(1);
    }
}
