<?php

declare(strict_types=1);

/**
 * The `default` half of malformed-case.php: a `switch` that closes, holding a
 * `default` whose colon is missing, so the tokenizer assigns that one arm no
 * scope while the switch itself keeps both of its own scope pointers.
 *
 * `default` carries no label to read, so it is counted rather than parsed — and
 * that is the trap. An arm PHP cannot parse is no evidence of a branch, so
 * counting it hands the switch a fourth branch it does not have and reports a
 * file PHP rejects.
 *
 * The two `case` arms qualify on their own and leave the file one branch below
 * the minimum, so counting the unreadable `default` is the single thing that
 * would carry it over and report.
 */

switch ($shape->type) {
    case 'circle':
        return 'Circle';
    case 'square':
        return 'Square';
    default
}
