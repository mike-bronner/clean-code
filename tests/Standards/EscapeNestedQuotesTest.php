<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

/**
 * Behaviour tests for the CleanCode.Strings.EscapeNestedQuotes sniff (#25):
 * prefer a double-quoted string with escaped inner quotes over switching to
 * single quotes to dodge the escape.
 *
 * compliant.inc covers the accepted forms — a properly escaped double-quoted
 * string, single-quoted strings without a nested double quote, an empty
 * string, and interpolation. violations.inc flags single-quoted literals that
 * carry a double quote: the plain cases (lines 3-4) are auto-fixable, while
 * literals containing a variable (`$`) or a backslash escape (lines 5-6) are
 * detection-only because re-delimiting them could change meaning.
 */
class EscapeNestedQuotesTest extends StringsSniffTestCase
{
    protected function sniffCode(): string
    {
        return 'CleanCode.Strings.EscapeNestedQuotes';
    }

    protected function fixtureDirectory(): string
    {
        return 'EscapeNestedQuotes';
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
            ],
            $this->errorCountsByLine($file)
        );
    }

    public function testOnlyMeaningPreservingCasesAreAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(2, $file->getFixableCount());
    }

    public function testFixerEscapesNestedQuotesUnderDoubleQuotes(): void
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
