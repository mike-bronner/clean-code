<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards;

/**
 * Behaviour tests for the CleanCode.Strings.RequireHeredocForMarkup sniff
 * (#25): HTML/markup embedded in a regular quoted string should live in a
 * HereDoc instead.
 *
 * This sniff is detection-only — converting an inline string to a HereDoc is a
 * structural edit the standard leaves to the developer — so there are no
 * autofix fixtures. compliant.inc proves a real HereDoc is accepted, including
 * one with deeply nested markup, and that comparisons/generics (`a < b`,
 * `List<int>`), shell redirection (`<input.txt >out`) and a C include
 * (`<time.h>`) are not mistaken for an `<input>`/`<time>` element;
 * violations.inc flags markup in both single- and double-quoted strings.
 */
class RequireHeredocForMarkupTest extends StringsSniffTestCase
{
    protected function sniffCode(): string
    {
        return 'CleanCode.Strings.RequireHeredocForMarkup';
    }

    protected function fixtureDirectory(): string
    {
        return 'RequireHeredocForMarkup';
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

    public function testViolationsAreFlaggedAtTheExpectedColumns(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(
            [
                3 => [11],
                4 => [15],
                5 => [16],
                6 => [10],
            ],
            $this->errorColumnsByLine($file)
        );
    }

    public function testMarkupViolationsAreNotAutoFixable(): void
    {
        $file = $this->processFixture('violations.inc');

        self::assertSame(0, $file->getFixableCount());
    }
}
