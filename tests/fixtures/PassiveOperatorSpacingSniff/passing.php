<?php

declare(strict_types=1);

namespace App\Fixtures;

function passiveOperators(int $number, string $dir): void
{
    // The four operators this sniff owns, each flush against its operand.
    $identity = +$number;
    $negation = -$number;
    $suppressed = @file_get_contents('x');
    $executed = `ls`;
    $interpolated = `ls $dir`;

    // A sign acting on a cross-direction increment/decrement still fuses into
    // nothing, so the flush form is the compliant one.
    $signOnIncrement = -++$number;
    $signOnDecrement = +--$number;

    // Error control over a sign, flush on both operators.
    $suppressedNegation = @-$number;
    $suppressedIdentity = @+$number;
}

/**
 * Near-miss shapes the sniff must stay silent on. A spaced *binary* `+`/`-` is
 * correct under the binary-operator standard (#35); reporting any of these
 * would put the two standards in direct contradiction.
 */
function binaryOperatorsAreNotPassive(int $first, int $second): int
{
    $plus = $first + $second;
    $minus = $first - $second;

    // The left operand ends in a postfix increment/decrement, a closing
    // bracket, a literal or a heredoc close — all values, so every `+`/`-`
    // below is binary and keeps its spaces.
    $afterPostfix = $first++ + $second;
    $afterPrefixDecrement = $first-- - $second;
    $afterParenthesis = ($first) - $second;
    $afterIndex = [$first][0] + $second;
    $afterLiteral = 3 - $second;

    // Whitespace spanning a line break is wrapping, not passive-operator
    // spacing — owned by CleanCode.Operators.OperatorLineBreak.
    $wrapped = -
        $first;

    return $plus + $minus + $afterPostfix + $afterPrefixDecrement
        + $afterParenthesis + $afterIndex + $afterLiteral + $wrapped;
}

/**
 * A same-direction sign pair is deliberately left alone: closing the gap would
 * fuse `- -$a` into `--$a` and silently change the meaning to a pre-decrement.
 */
function guardedSignPairs(int $number): void
{
    $doubleNegation = - -$number;
    $doubleIdentity = + +$number;
}

/**
 * Backticks already flush against their command, including the empty and
 * whitespace-free forms, and two execution strings sharing one line.
 */
function compliantBackticks(string $dir): void
{
    $pair = `ls` . `pwd`;
    $nested = `ls $dir/sub`;
}
