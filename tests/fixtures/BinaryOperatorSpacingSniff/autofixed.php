<?php

declare(strict_types=1);

namespace App\Fixtures;

function binarySpacing(int $first, int $second): int
{
    $noSpaceBefore = $first + $second;
    $noSpaceAfter = $first + $second;
    $tooMuchSpaceBefore = $first + $second;
    $tooMuchSpaceAfter = $first + $second;

    // A binary sign whose left operand is a postfix increment: the sign is
    // still binary, so the missing spaces are reported here rather than being
    // mistaken for the passive standard's flush unary form.
    $afterPostfix = $first++ + $second;

    return $noSpaceBefore + $noSpaceAfter + $tooMuchSpaceBefore
        + $tooMuchSpaceAfter + $afterPostfix;
}
