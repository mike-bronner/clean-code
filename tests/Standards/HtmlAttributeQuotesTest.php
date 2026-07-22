<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

/**
 * Behaviour tests for the CleanCode.Strings.HtmlAttributeQuotes sniff (#25):
 * HTML attributes inside string literals must use double quotes, not
 * apostrophes.
 *
 * compliant.inc guards the important non-matches — already-double-quoted
 * attributes, tags without attributes, non-HTML SQL such as `name = 'admin'`,
 * and single-quoted PHP strings (out of scope for this sniff). violations.inc
 * flags one error per string token regardless of how many apostrophe
 * attributes it holds; the fixer rewrites them all to escaped double quotes.
 */
class HtmlAttributeQuotesTest extends StringsSniffTestCase
{
    protected function sniffCode(): string
    {
        return 'CleanCode.Strings.HtmlAttributeQuotes';
    }

    protected function fixtureDirectory(): string
    {
        return 'HtmlAttributeQuotes';
    }

    public function testViolationsAreFlaggedAtTheExpectedLines(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(
            [
                3 => 1,
                4 => 1,
                5 => 1,
            ],
            $this->errorCountsByLine($file)
        );
    }

    public function testFixerRewritesApostrophesToDoubleQuotes(): void
    {
        self::assertStringEqualsFile(
            $this->fixturePath('autofix-after.inc'),
            $this->fixedContent('autofix-before.inc')
        );
    }

    public function testFixedFixturePassesWithNoViolations(): void
    {
        $file = $this->processFixture('autofix-after.inc');

        self::assertSame([], $this->errorCountsByLine($file));
    }
}
