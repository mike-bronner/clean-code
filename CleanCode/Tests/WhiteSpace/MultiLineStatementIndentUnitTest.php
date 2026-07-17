<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\WhiteSpace;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.WhiteSpace.MultiLineStatementIndent sniff.
 *
 * The line maps below refer to MultiLineStatementIndentUnitTest.inc.
 */
class MultiLineStatementIndentUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            76 => 1,
            77 => 1,
            81 => 1,
            83 => 1,
            87 => 1,
            94 => 1,
            100 => 1,
            104 => 1,
            105 => 1,
            111 => 1,
            117 => 1,
            125 => 1,
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
