<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\ClearCode;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.ClearCode.OneThoughtPerLine sniff.
 *
 * The line maps below refer to OneThoughtPerLineUnitTest.inc.
 */
class OneThoughtPerLineUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            25 => 2,
            26 => 1,
            27 => 1,
            28 => 1,
            29 => 1,
            30 => 1,
            31 => 1,
            34 => 1,
            36 => 1,
            37 => 1,
            38 => 1,
            42 => 1,
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
