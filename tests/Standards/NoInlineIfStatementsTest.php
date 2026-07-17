<?php

declare(strict_types=1);

namespace MikeBronner\Tests\Standards;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Tests the "Conditionals: No Inline If-Statements" standard, enforced by
 * wiring Generic.ControlStructures.InlineControlStructure into rules.xml.
 *
 * The line map below refers to NoInlineIfStatementsTest.inc; the expected
 * auto-fixed source lives in NoInlineIfStatementsTest.inc.fixed.
 */
class NoInlineIfStatementsTest extends TestCase
{
    private const SNIFF = 'Generic.ControlStructures.InlineControlStructure';

    public function testFlagsInlineConditionalsAtTheExpectedLines(): void
    {
        $file = $this->processFixture();

        $expected = [
            25 => 1,
            28 => 1,
            29 => 1,
            32 => 1,
            33 => 1,
            34 => 1,
            37 => 2,
            41 => 1,
            46 => 1,
        ];

        self::assertSame($expected, $this->errorCountsByLine($file));
    }

    public function testEveryViolationIsAutoFixable(): void
    {
        $file = $this->processFixture();

        self::assertGreaterThan(0, $file->getErrorCount());
        self::assertSame($file->getErrorCount(), $file->getFixableCount());
    }

    public function testAddsBracesWhenFixed(): void
    {
        $file = $this->processFixture();
        $file->fixer->fixFile();

        self::assertStringEqualsFile(
            __DIR__ . '/NoInlineIfStatementsTest.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(): LocalFile
    {
        $config = new Config();
        $config->cache = false;
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];
        $config->sniffs = [self::SNIFF];

        $file = new LocalFile(
            __DIR__ . '/NoInlineIfStatementsTest.inc',
            new Ruleset($config),
            $config
        );
        $file->process();

        return $file;
    }

    /**
     * @return array<int, int> line number => error count
     */
    private function errorCountsByLine(LocalFile $file): array
    {
        $counts = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                $counts[$line] = ($counts[$line] ?? 0) + count($errors);
            }
        }

        ksort($counts);

        return $counts;
    }
}
