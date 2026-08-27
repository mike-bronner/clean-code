<?php

declare(strict_types=1);

/**
 * A `switch` that closes, holding a nested `switch` written with no body at
 * all, so the tokenizer assigns the nested one neither scope pointer while the
 * outer keeps both of its own.
 *
 * This is the reachable route to a nested switch with no scope_closer. A file
 * truncated after the nested switch's own opening brace loses the outer's
 * closing brace with it, and the scope check turns the outer away before any
 * arm is read; omitting the body instead leaves the outer intact and the walk
 * really does reach a nested switch it cannot jump over.
 *
 * The subject is a one-hop discriminator read, so the arm walk runs rather than
 * being skipped at the subject: the nested switch is reached. The outer holds
 * two arms, one branch below the shipped minimum, which is what keeps the file
 * silent — the assertion is that the run finishes and says nothing, not that
 * some other rule turned it away first.
 *
 * Without the isset() guard the walk would assign the absent closer to its own
 * pointer, restart from the top of the file, reach this same nested switch
 * again, and never terminate.
 */

function unbodied(object $shape, object $inner): string
{
    switch ($shape->type) {
        case 'circle':
            switch ($inner->kind)

            return 'Circle';
        case 'square':
            return 'Square';
    }

    return '';
}
