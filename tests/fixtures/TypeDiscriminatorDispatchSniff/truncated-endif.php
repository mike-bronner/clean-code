<?php

declare(strict_types=1);

/**
 * The same cut inside an alternative-syntax construct, so the body walk starts
 * on an `endif` rather than a brace. The enclosing `if` never closes, so that
 * `endif` carries no scope pointer to recognise it by — its keyword is the only
 * thing that says the walk has left the construct the body lives in.
 *
 * The `else` after it belongs to the outer chain. A walk that read on past the
 * `endif` and its semicolon would take that `else` as the inner chain's third
 * branch and report it.
 */

if ($shape->type === 'circle'):
    if ($shape->type === 'square')
        return 'Square';
    elseif ($shape->type === 'rect')
endif;
else:
    return 'Unknown';
endif;
