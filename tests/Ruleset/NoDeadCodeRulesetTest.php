<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset;

use PHPUnit\Framework\TestCase;

class NoDeadCodeRulesetTest extends TestCase
{
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
