<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Conditionals;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Conditionals.OneConditionPerLine sniff.
 *
 * The line maps below refer to OneConditionPerLineUnitTest.inc.
 */
class OneConditionPerLineUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            68 => 1,
            75 => 1,
            84 => 1,
            89 => 1,
            90 => 1,
            97 => 1,
            104 => 1,
            110 => 1,
            117 => 1,
            124 => 1,
            129 => 1,
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
