<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

/**
 * Behaviour tests for the CleanCode.Strings.RequireStringInterpolation sniff
 * (#25): concatenation of a string literal with a variable should be an
 * interpolated string.
 *
 * violations.inc pins which concatenations are flagged and where: the direct
 * two-operand cases (lines 3-5) are auto-fixable, while multi-expression
 * chains (line 6) and complex variable operands — property, index, method
 * (lines 7-9) — are detection-only. autofix-before/after cover only the
 * fixable cases so the fixed output re-runs clean.
 */
class RequireStringInterpolationTest extends StringsSniffTestCase
{
    protected function sniffCode(): string
    {
        return 'CleanCode.Strings.RequireStringInterpolation';
    }

    protected function fixtureDirectory(): string
    {
        return 'RequireStringInterpolation';
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
                8 => 1,
                9 => 1,
            ],
            $this->errorCountsByLine($file)
        );
    }

    public function testOnlyTheDirectTwoOperandCasesAreAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(3, $file->getFixableCount());
    }

    public function testFixerConvertsConcatenationToInterpolation(): void
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
