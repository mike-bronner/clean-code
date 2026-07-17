<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\WhiteSpace;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.WhiteSpace.BlankLines sniff.
 *
 * The line maps below refer to BlankLinesUnitTest.inc; the expected
 * auto-fixed output lives in BlankLinesUnitTest.inc.fixed.
 */
class BlankLinesUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
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
        ];
    }

    /**
     * @return array<int, int> line number => expected warning count
     */
    protected function getWarningList(): array
    {
        return [];
    }
}
