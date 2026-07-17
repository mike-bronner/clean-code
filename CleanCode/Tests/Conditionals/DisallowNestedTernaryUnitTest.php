<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Conditionals;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Conditionals.DisallowNestedTernary sniff.
 *
 * The line maps below refer to DisallowNestedTernaryUnitTest.inc.
 */
class DisallowNestedTernaryUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            43 => 1,
            46 => 1,
            49 => 1,
            52 => 1,
            56 => 1,
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
