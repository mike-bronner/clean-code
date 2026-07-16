<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Models;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Models.DisallowExternalPersistenceCalls sniff.
 *
 * The line maps below refer to DisallowExternalPersistenceCallsUnitTest.inc.
 */
class DisallowExternalPersistenceCallsUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [];
    }

    /**
     * @return array<int, int> line number => expected warning count
     */
    protected function getWarningList(): array
    {
        return [
            3 => 1,
            4 => 1,
            5 => 1,
            6 => 1,
            7 => 1,
            8 => 1,
            9 => 1,
            10 => 1,
            11 => 1,
            28 => 1,
        ];
    }
}
