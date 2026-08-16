<?php

declare(strict_types=1);

namespace App\Fixtures;

function passiveOperators(int $number, string $dir): void
{
    $identity = +$number;
    $negation = -$number;
    $suppressed = @file_get_contents('x');
    $executed = `ls`;
    $interpolated = `ls $dir`;
    $signOnIncrement = -++$number;
    $signOnDecrement = +--$number;
}

/**
 * Error control before a bare sign. Both operators are spaced, so both are
 * reported on the same line, and the fixed form `@-$number` satisfies both.
 */
function errorControlOverASign(int $number): void
{
    $suppressedNegation = @-$number;
    $suppressedIdentity = @+$number;
}

/**
 * A sign opening a statement, and a sign opening a PHP block. Neither can have
 * a left operand, so both are unary negations this standard owns.
 */
function statementLeadingSign(int $number): void
{
    $assigned = $number;
    -$number;
}
?>
<?= -$number ?>
