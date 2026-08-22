<?php

declare(strict_types=1);

/**
 * Continuation-shape fixture for CleanCode.Conditionals.TypeDiscriminatorDispatch.
 *
 * failing.php writes each chain the one way — braced, with a merged `elseif`,
 * and a braced `switch`. PHP spells both constructs more ways than that, and
 * PHP_CodeSniffer models each spelling differently: a spaced `else if` puts the
 * scope on the trailing `if` and leaves the `else` with none, a brace-less
 * clause has no scope at all, and an alternative-syntax clause closes on the
 * *next clause's keyword* rather than on a brace. A walk that assumed the braced
 * layout would silently miss all of them, so each gets its own construct here.
 *
 * The spaced `else if` chain doubles as the guard against double-reporting: its
 * trailing `if` is a T_IF the sniff is dispatched on in its own right, and must
 * stay silent because the chain is already reported at its head.
 */

function mergedElseif(object $shape): string
{
    if ($shape->type === 'circle') {
        return 'Circle';
    } elseif ($shape->type === 'square') {
        return 'Square';
    } else {
        return 'Unknown';
    }
}

function spacedElseIf(object $shape): string
{
    if ($shape->type === 'circle') {
        return 'Circle';
    } else if ($shape->type === 'square') {
        return 'Square';
    } else {
        return 'Unknown';
    }
}

function braceless(object $shape): string
{
    if ($shape->type === 'circle')
        return 'Circle';
    elseif ($shape->type === 'square')
        return 'Square';
    else
        return 'Unknown';
}

function alternativeIf(object $shape): string
{
    if ($shape->type === 'circle'):
        return 'Circle';
    elseif ($shape->type === 'square'):
        return 'Square';
    else:
        return 'Unknown';
    endif;
}

function alternativeSwitch(object $shape): string
{
    switch ($shape->type):
        case 'circle':
            return 'Circle';
        case 'square':
            return 'Square';
        default:
            return 'Unknown';
    endswitch;
}

/**
 * The double-report guard needs a chain long enough for its own *tail* to
 * qualify. In a three-branch spaced `else if`, the trailing `if` heads only two
 * branches and is dropped by the threshold whether or not the guard exists — so
 * a three-branch chain cannot tell the two apart. Here the tail is itself three
 * branches long, so dropping the guard reports this chain twice.
 */
function longSpacedElseIf(object $shape): string
{
    if ($shape->type === 'circle') {
        return 'Circle';
    } else if ($shape->type === 'square') {
        return 'Square';
    } else if ($shape->type === 'rect') {
        return 'Rectangle';
    } else {
        return 'Unknown';
    }
}
