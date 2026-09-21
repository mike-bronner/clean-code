<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the No Dead Code standard as wired into the master
 * CleanCode/ruleset.xml: third-party rules (Squiz commented-out code, Slevomat unused
 * imports) plus the custom UnusedPrivateElements and UnusedFormalParameter
 * sniffs, exercised through the phpcs/phpcbf CLI against the real CleanCode/ruleset.xml.
 *
 * The unused-parameter half of this standard was carried by
 * SlevomatCodingStandard.Functions.UnusedParameter until #120 landed, and is
 * now carried by CleanCode.DeadCode.UnusedFormalParameter. The replacement is
 * a strict superset on everything this fixture exercises; it differs only by
 * exempting a method annotated @inheritdoc or #[\Override], or overriding a
 * parent declared in the same file.
 *
 * The runs scope to this standard's own sniffs via --sniffs (see SNIFFS): the
 * fixtures are clean only of dead code, not of every other standard sharing
 * the master ruleset (line length, one-thought-per-line, …), so scoping keeps
 * the zero-violation assertions honest and means later additions to CleanCode/ruleset.xml
 * cannot break this test — matching the TypeHints and Exceptions ruleset tests.
 * Wiring is still proven: --sniffs only filters the loaded ruleset, so a sniff
 * removed from CleanCode/ruleset.xml drops out of the report and its negative-case
 * assertion fails.
 *
 * Fixtures live in tests/Ruleset/fixtures/ and use the .inc extension so the
 * PSR-12 self-lint (which scans .php only) ignores their intentional
 * violations; the phpcs invocation opts back in via --extensions=inc.
 */
class NoDeadCodeRulesetTest extends TestCase
{
    /**
     * The sniffs the No Dead Code standard owns, as wired into CleanCode/ruleset.xml. The
     * phpcs/phpcbf runs scope to these so that sibling standards sharing the
     * master ruleset cannot trip the zero-violation fixtures.
     */
    private const SNIFFS = 'Squiz.PHP.CommentedOutCode,'
        . 'CleanCode.DeadCode.UnusedFormalParameter,'
        . 'SlevomatCodingStandard.Namespaces.UnusedUses,'
        . 'CleanCode.DeadCode.UnusedPrivateElements';

    public function testCleanFileProducesZeroViolations(): void
    {
        $report = $this->runPhpcs($this->fixture('clean.inc'));

        self::assertSame(0, $report['totals']['errors']);
        self::assertSame(0, $report['totals']['warnings']);
    }

    public function testCommentedOutCodeIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationsAt('Squiz.PHP.CommentedOutCode.Found', [[16, 9]]);
    }

    public function testUnusedPrivatePropertyIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationsAt('CleanCode.DeadCode.UnusedPrivateElements.UnusedProperty', [[12, 20]]);
    }

    public function testUnusedPrivateMethodIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationsAt('CleanCode.DeadCode.UnusedPrivateElements.UnusedMethod', [[24, 22]]);
    }

    /**
     * Column 46 is $unusedTax. Pinning it matters more here than anywhere else
     * in this file: $subtotal, the parameter that *is* read, sits on the same
     * line at column 30, so a line-only assertion would pass just as happily
     * with both parameters flagged.
     */
    public function testUnusedParameterIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationsAt('CleanCode.DeadCode.UnusedFormalParameter.Found', [[14, 46]]);
    }

    public function testUnusedImportIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationsAt('SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse', [[7, 1]]);
    }

    public function testExplanatoryCommentsAndDocBlocksAreNotFlagged(): void
    {
        $report = $this->runPhpcs($this->fixture('edge-cases.inc'));

        self::assertSame(0, $report['totals']['errors']);
        self::assertSame(0, $report['totals']['warnings']);
    }

    public function testPhpcbfRemovesUnusedImport(): void
    {
        $scratch = sys_get_temp_dir() . '/no-dead-code-' . uniqid() . '.inc';
        copy($this->fixture('fixable.inc'), $scratch);

        try {
            exec(sprintf(
                '%s --standard=%s --sniffs=%s --extensions=inc %s',
                escapeshellarg($this->binary('phpcbf')),
                escapeshellarg($this->ruleset()),
                escapeshellarg(self::SNIFFS),
                escapeshellarg($scratch)
            ));

            self::assertStringEqualsFile($scratch, file_get_contents($this->fixture('fixable.inc.fixed')));
        } finally {
            unlink($scratch);
        }
    }

    /**
     * Asserts that $source fires on violations.inc at exactly the given
     * [line, column] positions — no more, no fewer.
     *
     * Exclusivity is the point. Asserting only that a violation is *present*
     * at a line lets a regression that also flags a healthy neighbour pass
     * unnoticed, and violations.inc pairs a used and an unused member on the
     * same line precisely to make that possible ($subtotal beside $unusedTax,
     * line 14). Columns are part of the tuple for the same reason: same line,
     * different member.
     *
     * @param list<array{0: int, 1: int}> $positions
     */
    private function assertViolationsAt(string $source, array $positions): void
    {
        $reported = [];

        foreach ($this->runPhpcs($this->fixture('violations.inc'))['files'] as $file) {
            foreach ($file['messages'] as $message) {
                $reported[$message['source']][] = [$message['line'], $message['column']];
            }
        }

        self::assertSame($positions, $reported[$source] ?? [], sprintf(
            'Expected %s at exactly %s; got: %s',
            $source,
            var_export($positions, true),
            var_export($reported, true)
        ));
    }

    /**
     * @return array{totals: array{errors: int, warnings: int}, files: array<string, array{messages: list<array{
     *     line: int, type: string, source: string, message: string
     * }>}>}
     */
    private function runPhpcs(string $fixture): array
    {
        exec(sprintf(
            '%s --standard=%s --sniffs=%s --extensions=inc --report=json -q %s',
            escapeshellarg($this->binary('phpcs')),
            escapeshellarg($this->ruleset()),
            escapeshellarg(self::SNIFFS),
            escapeshellarg($fixture)
        ), $output);

        $report = json_decode(implode('', $output), true);

        self::assertIsArray($report, 'phpcs did not produce a JSON report: ' . implode("\n", $output));

        return $report;
    }

    private function fixture(string $name): string
    {
        return __DIR__ . '/fixtures/' . $name;
    }

    private function binary(string $name): string
    {
        return dirname(__DIR__, 2) . '/vendor/bin/' . $name;
    }

    private function ruleset(): string
    {
        return dirname(__DIR__, 2) . '/CleanCode/ruleset.xml';
    }
}
