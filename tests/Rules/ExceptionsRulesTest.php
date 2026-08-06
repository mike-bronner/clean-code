<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Rules;

use MikeBronner\CleanCode\Tests\ThirdPartyStandards;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

/**
 * Evaluates the Slevomat rules wired into the master rules.xml for the
 * Exceptions standard: catches must reference \Throwable rather than the
 * general \Exception, and a caught variable that is never used must be
 * dropped (non-capturing catch).
 *
 * Coverage comes in two parts. A wiring test parses the master rules.xml
 * through PHPCS's real ruleset path and asserts both rules are registered,
 * so dropping or misspelling a <rule ref> breaks the build. The behaviour
 * tests then pin each sniff in isolation (via $config->sniffs) against its
 * fixture, keeping the line maps and auto-fix output independent of other
 * rules that land in the master ruleset later.
 *
 * The line maps below refer to the fixtures in Fixtures/.
 */
class ExceptionsRulesTest extends TestCase
{
    private const REFERENCE_THROWABLE_ONLY = 'SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly';

    private const REQUIRE_NON_CAPTURING_CATCH = 'SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch';

    public function testMasterRulesetRegistersBothExceptionRules(): void
    {
        $ruleset = new Ruleset($this->masterRulesetConfig());

        $this->assertArrayHasKey(self::REFERENCE_THROWABLE_ONLY, $ruleset->sniffCodes);
        $this->assertArrayHasKey(self::REQUIRE_NON_CAPTURING_CATCH, $ruleset->sniffCodes);
    }

    public function testReferenceThrowableOnlyFlagsGeneralExceptionCatches(): void
    {
        $file = $this->processFixture(self::REFERENCE_THROWABLE_ONLY, 'ReferenceThrowableOnly.inc');

        $this->assertSame(
            [6 => 1, 13 => 1, 20 => 1, 62 => 1, 73 => 1],
            $this->errorLines($file, self::REFERENCE_THROWABLE_ONLY . '.ReferencedGeneralException')
        );
    }

    public function testReferenceThrowableOnlyFixesGeneralExceptionToThrowable(): void
    {
        $file = $this->processFixture(self::REFERENCE_THROWABLE_ONLY, 'ReferenceThrowableOnly.inc');

        $this->assertSame($file->getErrorCount(), $file->getFixableCount());

        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/ReferenceThrowableOnly.inc.fixed',
            $file->fixer->getContents()
        );
    }

    public function testRequireNonCapturingCatchFlagsUnusedCaptures(): void
    {
        $file = $this->processFixture(self::REQUIRE_NON_CAPTURING_CATCH, 'RequireNonCapturingCatch.inc');

        $this->assertSame(
            [20 => 1, 27 => 1, 42 => 1, 53 => 1],
            $this->errorLines($file, self::REQUIRE_NON_CAPTURING_CATCH . '.NonCapturingCatchRequired')
        );
    }

    public function testRequireNonCapturingCatchFixesUnusedCaptures(): void
    {
        $file = $this->processFixture(self::REQUIRE_NON_CAPTURING_CATCH, 'RequireNonCapturingCatch.inc');

        $this->assertSame($file->getErrorCount(), $file->getFixableCount());

        $file->fixer->fixFile();

        $this->assertStringEqualsFile(
            __DIR__ . '/Fixtures/RequireNonCapturingCatch.inc.fixed',
            $file->fixer->getContents()
        );
    }

    private function processFixture(string $sniffCode, string $fixture): LocalFile
    {
        $config = $this->masterRulesetConfig();
        $config->sniffs = [$sniffCode];

        $file = new LocalFile(__DIR__ . '/Fixtures/' . $fixture, new Ruleset($config), $config);
        $file->process();

        return $file;
    }

    private function masterRulesetConfig(): Config
    {
        // Pin installed_paths explicitly: the AbstractSniffUnitTest harness
        // blanks the static Config data (via ConfigDouble), which would
        // otherwise silently deregister the third-party standards here.
        Config::setConfigData(
            'installed_paths',
            ThirdPartyStandards::installedPaths(),
            true
        );

        // The argv must be non-empty: Config falls back to parsing the live
        // $_SERVER['argv'] as PHPCS flags when given none, which would leak
        // unrelated PHPUnit arguments (e.g. --filter) into the shared Config.
        $config = new Config(['--standard=' . dirname(__DIR__, 2) . '/rules.xml']);
        $config->cache = false;

        return $config;
    }

    /**
     * @return array<int, int> line number => error count, asserting every
     *                         error comes from the expected sniff code
     */
    private function errorLines(LocalFile $file, string $expectedSource): array
    {
        $lines = [];

        foreach ($file->getErrors() as $line => $columns) {
            foreach ($columns as $errors) {
                foreach ($errors as $error) {
                    $this->assertSame($expectedSource, $error['source']);
                    $lines[$line] = ($lines[$line] ?? 0) + 1;
                }
            }
        }

        ksort($lines);

        return $lines;
    }
}
