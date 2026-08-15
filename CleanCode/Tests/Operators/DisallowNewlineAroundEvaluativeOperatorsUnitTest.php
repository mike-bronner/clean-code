<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Operators;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

/**
 * Unit test for the CleanCode.Operators.DisallowNewlineAroundEvaluativeOperators sniff.
 *
 * The line maps below refer to DisallowNewlineAroundEvaluativeOperatorsUnitTest.inc.
 */
class DisallowNewlineAroundEvaluativeOperatorsUnitTest extends AbstractSniffUnitTest
{
    /**
     * @return array<int, int> line number => expected error count
     */
    protected function getErrorList(): array
    {
        return [
            // Newline before the operator, one case per operator.
            31 => 1,
            33 => 1,
            35 => 1,
            37 => 1,
            39 => 1,
            41 => 1,
            43 => 1,
            45 => 1,
            47 => 1,
            49 => 1,
            51 => 1,
            // Newline after the operator, one case per operator.
            54 => 1,
            56 => 1,
            58 => 1,
            60 => 1,
            62 => 1,
            64 => 1,
            66 => 1,
            68 => 1,
            70 => 1,
            72 => 1,
            74 => 1,
            // Newlines on both sides of the operator, one case per operator.
            79 => 2,
            82 => 2,
            85 => 2,
            88 => 2,
            91 => 2,
            94 => 2,
            97 => 2,
            100 => 2,
            103 => 2,
            106 => 2,
            109 => 2,
            // instanceof with a fully qualified class name on the next line.
            113 => 1,
            // Operators nested inside multiline array/argument lists.
            121 => 1,
            124 => 1,
            // Operator split inside a ternary condition.
            130 => 1,
            // Comment in the gap: reported but not auto-fixable.
            134 => 1,
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
