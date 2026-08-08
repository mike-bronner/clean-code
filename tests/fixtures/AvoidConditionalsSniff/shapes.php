<?php

declare(strict_types=1);

/**
 * Every *variant* shape of the four reported constructs, so a token-walk that
 * only ever handled the plain braced form in failing.php cannot pass.
 *
 * The shapes that have historically slipped past sniffs in this package are
 * the brace-less and alternative-syntax control structures (no `scope_opener`
 * / a T_COLON body), `else if` written as two tokens rather than one
 * T_ELSEIF, the short ternary `?:` whose `?` and `:` are adjacent, a nested
 * ternary, and a ternary inside an arrow function — where the `=>` sits
 * between the arrow-fn token and the `?`.
 */

$amount = 5;
$fallback = null;

// Brace-less if: no scope_opener on the T_IF.
if ($amount > 3)
    $amount++;

// `else if` as two tokens: the T_IF is reported, the T_ELSE is not.
if ($amount > 4) {
    $amount++;
} else if ($amount > 2) {
    $amount--;
}

// Alternative syntax: the body is a T_COLON, closed by T_ENDIF.
if ($amount > 1):
    $amount++;
elseif ($amount > 0):
    $amount--;
endif;

// Alternative-syntax switch, closed by T_ENDSWITCH.
switch ($amount):
    case 1:
        break;
    default:
        break;
endswitch;

// Short ternary: `?` and `:` are adjacent.
$short = $fallback ?: 'none';

// Nested ternary: two independent `?` tokens.
$nested = $amount > 3 ? 'high' : ($amount > 1 ? 'mid' : 'low');

// Ternary as a call argument.
$argument = strtoupper($amount > 3 ? 'high' : 'low');

// Ternary inside an arrow function.
$arrow = fn (int $value): string => $value > 3 ? 'high' : 'low';
