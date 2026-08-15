<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the No Dead Code standard as wired into the master
 * rules.xml: third-party rules (Squiz commented-out code, Slevomat unused
 * parameter / unused imports) plus the custom UnusedPrivateElements sniff,
 * exercised through the phpcs/phpcbf CLI against the real rules.xml.
 *
 * The runs scope to this standard's own sniffs via --sniffs (see SNIFFS): the
 * fixtures are clean only of dead code, not of every other standard sharing
 * the master ruleset (line length, one-thought-per-line, …), so scoping keeps
 * the zero-violation assertions honest and means later additions to rules.xml
 * cannot break this test — matching the TypeHints and Exceptions ruleset tests.
 * Wiring is still proven: --sniffs only filters the loaded ruleset, so a sniff
 * removed from rules.xml drops out of the report and its negative-case
 * assertion fails.
 *
 * Fixtures live in tests/Ruleset/fixtures/ and use the .inc extension so the
 * PSR-12 self-lint (which scans .php only) ignores their intentional
 * violations; the phpcs invocation opts back in via --extensions=inc.
 */
class NoDeadCodeRulesetTest extends TestCase
{
    /**
     * The sniffs the No Dead Code standard owns, as wired into rules.xml. The
     * phpcs/phpcbf runs scope to these so that sibling standards sharing the
     * master ruleset cannot trip the zero-violation fixtures.
     */
    private const SNIFFS = 'Squiz.PHP.CommentedOutCode,'
        . 'SlevomatCodingStandard.Functions.UnusedParameter,'
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
        $this->assertViolationAt('Squiz.PHP.CommentedOutCode.Found', 16);
    }

    public function testUnusedPrivatePropertyIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationAt('CleanCode.DeadCode.UnusedPrivateElements.UnusedProperty', 12);
    }

    public function testUnusedPrivateMethodIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationAt('CleanCode.DeadCode.UnusedPrivateElements.UnusedMethod', 24);
    }

    public function testUnusedParameterIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationAt('SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter', 14);
    }

    public function testUnusedImportIsFlaggedAtTheCorrectLine(): void
    {
        $this->assertViolationAt('SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse', 7);
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

    private function assertViolationAt(string $source, int $line): void
    {
        $sourcesAtLines = [];

        foreach ($this->runPhpcs($this->fixture('violations.inc'))['files'] as $file) {
            foreach ($file['messages'] as $message) {
                $sourcesAtLines[$message['source']][] = $message['line'];
            }
        }

        self::assertContains($line, $sourcesAtLines[$source] ?? [], sprintf(
            'Expected %s at line %d; got: %s',
            $source,
            $line,
            var_export($sourcesAtLines, true)
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
        return dirname(__DIR__, 2) . '/rules.xml';
    }
}
