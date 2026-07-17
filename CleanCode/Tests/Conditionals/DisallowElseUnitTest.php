<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Conditionals;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Conditionals.DisallowElse sniff.
 *
 * The line maps below refer to DisallowElseUnitTest.inc; the expected
 * auto-fix output lives in DisallowElseUnitTest.inc.fixed.
 */
class DisallowElseUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            22 => 1,
            32 => 1,
            34 => 1,
            44 => 1,
            58 => 1,
            71 => 1,
            74 => 1,
            84 => 1,
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
