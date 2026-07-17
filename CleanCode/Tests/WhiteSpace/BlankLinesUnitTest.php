<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\WhiteSpace;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.WhiteSpace.BlankLines sniff.
 *
 * The line maps below refer to the fixture named in $testFile; each
 * fixture's expected auto-fixed output lives in its `.fixed` sibling.
 * The numbered fixtures cover blank lines directly after the opening
 * `<?php` tag, which must sit at the very start of a file.
 */
class BlankLinesUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(string $testFile = ''): array
    {
        if ($testFile === 'BlankLinesUnitTest.2.inc' || $testFile === 'BlankLinesUnitTest.3.inc') {
            return [3 => 1];
        }

        return [
            41 => 1,
            48 => 1,
            52 => 1,
            60 => 1,
            71 => 1,
            76 => 1,
            78 => 1,
            83 => 1,
            87 => 1,
            94 => 1,
            98 => 1,
            104 => 1,
            106 => 1,
            110 => 1,
            112 => 1,
            117 => 1,
            122 => 1,
            131 => 1,
            135 => 1,
            142 => 1,
            144 => 1,
            148 => 1,
            152 => 1,
            157 => 1,
            165 => 1,
        ];
    }

    /**
     * @return array<int, int> line number => expected warning count
     */
    protected function getWarningList(string $testFile = ''): array
    {
        return [];
    }
}
