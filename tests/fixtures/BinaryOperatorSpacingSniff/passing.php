<?php

declare(strict_types=1);

namespace App\Fixtures;

function binarySpacing(int $first, int $second): int
{
    $assigned = $first;
    $plus = $first + $second;
    $minus = $first - $second;
    $times = $first * $second;
    $compared = $first === $second;

    // The left operand ends in a value, so these stay binary and keep their
    // spaces exactly as the parent sniff requires.
    $afterPostfix = $first++ + $second;
    $afterParenthesis = ($first) - $second;

    return $assigned + $plus + $minus + $times + $afterPostfix + $afterParenthesis;
}

/**
 * The four contexts this sniff cedes to CleanCode.WhiteSpace.PassiveOperatorSpacing.
 * In each, no value can precede the sign, so it is a unary sign the passive
 * standard requires to sit flush — and this sniff must not ask for a space.
 *
 * Left unceded, the parent sniff re-spaces each of these on every phpcbf pass
 * while the passive sniff strips it again, and phpcbf abandons the whole file.
 */
function cededUnarySigns(int $number): void
{
    // T_ASPERAND — error control.
    $suppressedNegation = @-$number;
    $suppressedIdentity = @+$number;

    // T_SEMICOLON — the sign opens a new statement.
    -$number;
    +$number;
}
?>
<?php -$standaloneOpenTag; ?>
<?= -$shortEchoOpenTag ?>
