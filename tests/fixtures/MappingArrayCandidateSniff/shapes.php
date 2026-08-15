<?php

declare(strict_types=1);

/**
 * Continuation-shape fixture for CleanCode.Conditionals.MappingArrayCandidate.
 *
 * failing.php only ever writes a chain the one way — braced, with a merged
 * `elseif`. PHP has three more spellings, and PHP_CodeSniffer models each of
 * them differently: a spaced `else if` puts the scope on the trailing `if` and
 * leaves the `else` with none, a brace-less clause has no scope at all, and an
 * alternative-syntax clause closes on the *next clause's keyword* rather than on
 * a brace. A walk that assumed the braced layout would silently miss all three,
 * so each gets its own chain here.
 *
 * The spaced `else if` chain doubles as the guard against double-reporting: its
 * trailing `if` is a T_IF the sniff is dispatched on in its own right, and must
 * stay silent because the chain is already reported at its head.
 *
 * The last five chains mix the spellings and nest them, because a chain is not
 * obliged to pick one: a braced clause can be followed by a brace-less one, an
 * `else` can sit a line above its `if`, comments can separate any two clauses,
 * and a chain can live inside another chain's branch.
 */

function spacedElseIf(string $code): string
{
    if ($code === 'a') {
        return 'Alpha';
    } else if ($code === 'b') {
        return 'Bravo';
    } else {
        return 'Unknown';
    }
}

function braceless(string $code): string
{
    if ($code === 'a')
        return 'Alpha';
    elseif ($code === 'b')
        return 'Bravo';
    else
        return 'Unknown';
}

function bracelessSpacedElseIf(string $code): string
{
    if ($code === 'a')
        return 'Alpha';
    else if ($code === 'b')
        return 'Bravo';
    else
        return 'Unknown';
}

function alternativeSyntax(string $code): string
{
    if ($code === 'a'):
        return 'Alpha';
    elseif ($code === 'b'):
        return 'Bravo';
    else:
        return 'Unknown';
    endif;
}

function alternativeSyntaxWithoutDefault(int $level): string
{
    if ($level === 1):
        return 'low';
    elseif ($level === 2):
        return 'medium';
    elseif ($level === 3):
        return 'high';
    endif;

    return 'unknown';
}

function mixedBraceStyles(string $code): string
{
    if ($code === 'a') {
        return 'Alpha';
    } elseif ($code === 'b')
        return 'Bravo';
    else {
        return 'Unknown';
    }
}

function elseOnItsOwnLine(string $code): string
{
    if ($code === 'a') {
        return 'Alpha';
    }
    else
    if ($code === 'b') {
        return 'Bravo';
    }
    else {
        return 'Unknown';
    }
}

function separatedByComments(string $code): string
{
    if ($code === 'a') {
        // why Alpha
        return 'Alpha';
    } /* and then */ elseif ($code === 'b') {
        return 'Bravo';
    } // and finally
    else {
        return 'Unknown';
    }
}

/**
 * Only the *inner* chain qualifies. The outer chain's first branch holds a
 * whole `if` rather than a single value-producing statement, so it is excluded
 * — a chain is judged on its own bodies, never on its neighbours'.
 */
function nestedChain(string $code, string $inner): string
{
    if ($code === 'a') {
        if ($inner === 'x') {
            return 'AlphaX';
        } elseif ($inner === 'y') {
            return 'AlphaY';
        } else {
            return 'AlphaZ';
        }
    } elseif ($code === 'b') {
        return 'Bravo';
    } else {
        return 'Unknown';
    }
}

/**
 * The double-report guard needs a chain long enough for its own *tail* to
 * qualify. In a three-branch spaced `else if`, the trailing `if` heads only two
 * branches and is dropped by the threshold whether or not the guard exists — so
 * a three-branch chain cannot tell the two apart. Here the tail is itself three
 * branches long, so dropping the guard reports this chain twice.
 */
function longSpacedElseIf(string $code): string
{
    if ($code === 'a') {
        return 'Alpha';
    } else if ($code === 'b') {
        return 'Bravo';
    } else if ($code === 'c') {
        return 'Charlie';
    } else {
        return 'Unknown';
    }
}
