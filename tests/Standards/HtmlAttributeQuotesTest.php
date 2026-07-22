<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

/**
 * Behaviour tests for the CleanCode.Strings.HtmlAttributeQuotes sniff (#25):
 * HTML attributes inside string literals must use double quotes, not
 * apostrophes.
 *
 * compliant.inc guards the important non-matches — already-double-quoted
 * attributes (in both double- and single-quoted PHP strings), tags without
 * attributes, and non-HTML SQL such as `name = 'admin'`. violations.inc flags
 * one error per string token regardless of how many apostrophe attributes it
 * holds, and covers every context: a double-quoted PHP string, a single-quoted
 * PHP string with escaped-apostrophe attributes (line 6), a continuation line
 * of a multi-line string (line 9), and a value carrying a double quote (line 7)
 * that is reported but not auto-fixable. The fixer rewrites the fixable ones,
 * escaping the replacement quotes only where the PHP string context requires.
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
                6 => 1,
                7 => 1,
                9 => 1,
            ],
            $this->errorCountsByLine($file)
        );
    }

    public function testViolationsAreFlaggedAtTheExpectedColumns(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(
            [
                3 => [11],
                4 => [10],
                5 => [10],
                6 => [20],
                7 => [15],
                9 => [1],
            ],
            $this->errorColumnsByLine($file)
        );
    }

    public function testOnlyValuesWithoutDoubleQuotesAreAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        // Lines 3-6 and 9 are fixable; line 7's value contains a double quote,
        // so it is reported (addError) but left for manual conversion.
        self::assertSame(5, $file->getFixableCount());
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
