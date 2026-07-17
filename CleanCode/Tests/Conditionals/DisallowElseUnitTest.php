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
            96 => 1,
            107 => 1,
            120 => 1,
            131 => 1,
            138 => 1,
            149 => 1,
            157 => 1,
            166 => 1,
            179 => 1,
            190 => 1,
            205 => 1,
            217 => 1,
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
