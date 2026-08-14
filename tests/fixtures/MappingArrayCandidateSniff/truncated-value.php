<?php

declare(strict_types=1);

/**
 * A qualifying chain whose final branch is cut off inside its returned array,
 * so the last statement never reaches a semicolon.
 *
 * Every other rule here is satisfied: three branches, one subject, scalar
 * literals, and an expression built only from value tokens. The terminator is
 * the single thing missing — which is what makes this the fixture that pins the
 * check for one. A walk that reads the expression without first confirming the
 * statement ended would find nothing wrong with `[3, 4` and report the chain,
 * on a file PHP itself cannot parse.
 */

if ($code === 'a') {
    return 'Alpha';
} elseif ($code === 'b') {
    return 'Bravo';
} else
    return [3, 4
