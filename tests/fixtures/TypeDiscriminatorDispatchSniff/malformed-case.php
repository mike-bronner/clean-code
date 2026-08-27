<?php

declare(strict_types=1);

/**
 * A `switch` that closes, holding a `case` whose colon is missing, so the
 * tokenizer assigns that one arm no scope while the switch itself keeps both
 * of its own scope pointers.
 *
 * This is the reachable route to an arm with no scope_opener — a truncated file
 * loses the switch's closing brace too, and is turned away by the scope check
 * before any arm is read. Here the walk does reach the arm, and the label it
 * would read is bounded by a pointer the tokenizer never assigned.
 *
 * The other three arms qualify on their own, so the branch count clears the
 * minimum and the subject is a one-hop discriminator read: the unreadable label
 * is the single thing standing between this file and a report.
 */

switch ($shape->type) {
    case 'circle':
        return 'Circle';
    case 'square':
        return 'Square';
    case 'rect':
        return 'Rect';
    case 'oval'
}
