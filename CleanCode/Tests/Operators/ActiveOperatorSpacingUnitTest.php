<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Operators;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Operators.ActiveOperatorSpacing sniff.
 *
 * The line maps below refer to ActiveOperatorSpacingUnitTest.inc. Each
 * operator category has a missing-space-before, missing-space-after, and
 * missing-both line (the last expecting two errors); the unary ! has
 * no-space, extra-space, and chained-negation lines.
 */
class ActiveOperatorSpacingUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            53 => 1,
            54 => 1,
            55 => 2,
            56 => 1,
            57 => 1,
            58 => 2,
            59 => 1,
            60 => 1,
            61 => 2,
            62 => 1,
            63 => 1,
            64 => 2,
            65 => 1,
            66 => 1,
            67 => 2,
            68 => 1,
            69 => 1,
            70 => 2,
            71 => 1,
            72 => 1,
            73 => 2,
            76 => 1,
            77 => 1,
            78 => 2,
            79 => 1,
            80 => 1,
            81 => 2,
            82 => 1,
            83 => 1,
            84 => 2,
            85 => 1,
            86 => 1,
            87 => 2,
            88 => 1,
            89 => 1,
            90 => 2,
            93 => 1,
            94 => 1,
            95 => 2,
            96 => 1,
            97 => 1,
            98 => 2,
            101 => 1,
            102 => 1,
            103 => 2,
            104 => 1,
            105 => 1,
            106 => 2,
            107 => 1,
            108 => 1,
            109 => 2,
            110 => 1,
            111 => 1,
            112 => 2,
            113 => 1,
            114 => 1,
            115 => 2,
            116 => 1,
            117 => 1,
            118 => 2,
            121 => 1,
            122 => 1,
            123 => 2,
            126 => 1,
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
