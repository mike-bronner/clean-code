<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\DeadCode;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.DeadCode.UnusedPrivateElements sniff.
 *
 * The line maps below refer to UnusedPrivateElementsUnitTest.inc.
 */
class UnusedPrivateElementsUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            29 => 1,
            38 => 1,
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
