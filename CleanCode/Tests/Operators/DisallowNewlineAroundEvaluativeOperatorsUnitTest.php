<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Operators;

use PHP_CodeSniffer\Tests\Standards\AbstractSniffUnitTest;

class DisallowNewlineAroundEvaluativeOperatorsUnitTest extends AbstractSniffUnitTest
{
    protected function getErrorList(): array
    {
        return [
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
            113 => 1,
            121 => 1,
            124 => 1,
            130 => 1,
            134 => 1,
        ];
    }

    protected function getWarningList(): array
    {
        return [];
    }
}
