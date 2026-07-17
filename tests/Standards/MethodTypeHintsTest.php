<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Tests\ConfigDouble;
use PHPUnit\Framework\TestCase;

/**
 * Methods: Type Hints (#70) — verifies the Slevomat ParameterTypeHint and
 * ReturnTypeHint rules as configured in the master rules.xml.
 *
 * Third-party rules referenced from rules.xml cannot use PHPCS's
 * AbstractSniffUnitTest harness (it only resolves sniffs belonging to the
 * standard under test), so this test runs the master ruleset programmatically
 * against a line-mapped fixture.
 *
 * The whole ruleset runs unrestricted — a Config::$sniffs restriction would
 * drop the ruleset's message-level exclusions (MissingTraversableTypeHint-
 * Specification, UselessAnnotation) and the pinned enable* properties,
 * testing a config that never ships. Because the shipped ruleset also carries
 * PSR12 and other standards that flag the fixture's structure (multiple
 * classes, no namespace), the error assertion filters to Slevomat's
 * SlevomatCodingStandard.TypeHints.* sources by prefix. That prefix also
 * spans PropertyTypeHint (#45's sniff), but this fixture declares no
 * properties, so in practice only the ParameterTypeHint / ReturnTypeHint
 * sniffs #70 owns fire — the exclusions and property pins stay live while the
 * map pins exactly the parameter/return-hint behaviour. The line maps below
 * refer to Fixtures/MethodTypeHints.inc.
 */
class MethodTypeHintsTest extends TestCase
{
    private const TYPE_HINT_PREFIX = 'SlevomatCodingStandard.TypeHints.';

    /**
     * @return array<int, int> line number => expected error count
     */
    private function getErrorList(): array
    {
        return [
            95 => 1,
            103 => 1,
            112 => 1,
            120 => 1,
            125 => 1,
            130 => 2,
            139 => 1,
            148 => 1,
            159 => 1,
            175 => 1,
            183 => 1,
            191 => 1,
            199 => 1,
            207 => 1,
            249 => 2,
            261 => 1,
            269 => 1,
            284 => 1,
            292 => 1,
            300 => 1,
            319 => 1,
            327 => 1,
            343 => 1,
        ];
    }

    public function testFixtureViolationsAreReportedAtTheExpectedLines(): void
    {
        $file = $this->processFixture();

        $this->assertSame($this->getErrorList(), $this->typeHintErrorLines($file));

        // Forward guard: the TypeHints sniffs only ever addError, never
        // addWarning, so this is 0 today. Kept so a future Slevomat release (or
        // a severity change) that starts emitting a TypeHints *warning* — which
        // the error map above would silently miss — trips a failure here.
        $this->assertSame(0, $this->typeHintWarningCount($file));
    }

    public function testFixableViolationsProduceTheExpectedFixedFile(): void
    {
        $file = $this->processFixture();

        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/MethodTypeHints.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(): LocalFile
    {
        $config = new ConfigDouble();
        $config->standards = [dirname(__DIR__, 2) . '/rules.xml'];

        // ConfigDouble isolates the test from CodeSniffer.conf, which is
        // where the composer installer registers third-party standards, so
        // the Slevomat standard has to be registered in-memory here.
        Config::setConfigData(
            'installed_paths',
            dirname(__DIR__, 2) . '/vendor/slevomat/coding-standard',
            true
        );

        $file = new LocalFile(__DIR__ . '/Fixtures/MethodTypeHints.inc', new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    /**
     * Collapse the file's errors to a line => count map, counting every
     * SlevomatCodingStandard.TypeHints.* source and ignoring structural noise
     * (PSR1/PSR12) that the shipped ruleset also reports on the multi-class
     * fixture. The prefix spans PropertyTypeHint too, but the fixture declares
     * no properties, so only #70's ParameterTypeHint / ReturnTypeHint fire.
     *
     * @return array<int, int> line number => TypeHints error count
     */
    private function typeHintErrorLines(LocalFile $file): array
    {
        $lines = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    if (! str_starts_with($message['source'], self::TYPE_HINT_PREFIX)) {
                        continue;
                    }

                    $lines[$line] = ($lines[$line] ?? 0) + 1;
                }
            }
        }

        ksort($lines);

        return $lines;
    }

    private function typeHintWarningCount(LocalFile $file): int
    {
        $count = 0;

        foreach ($file->getWarnings() as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    if (str_starts_with($message['source'], self::TYPE_HINT_PREFIX)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
