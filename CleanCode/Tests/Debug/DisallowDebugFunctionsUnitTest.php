<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Debug;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Debug.DisallowDebugFunctions sniff.
 *
 * The line maps below refer to DisallowDebugFunctionsUnitTest.inc.
 */
class DisallowDebugFunctionsUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            3 => 1,
            4 => 1,
            5 => 1,
            6 => 1,
            7 => 1,
            24 => 1,
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
