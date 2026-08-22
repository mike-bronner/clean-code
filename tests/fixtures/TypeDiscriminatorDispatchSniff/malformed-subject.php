<?php

declare(strict_types=1);

/**
 * A `switch` whose subject parenthesis is never closed, in a file that still
 * gives the switch a body — so the tokenizer resolves its scope while leaving
 * the subject read unbounded.
 *
 * This is the reachable route to a switch with no parenthesis_closer: cutting
 * the file off inside the subject instead costs the switch its scope too, and
 * the scope check turns that away separately. Every other rule here is
 * satisfied — three scalar-literal arms, and a subject that reads as a one-hop
 * discriminator — so the unbounded subject is the single thing standing between
 * this file and a report.
 */

switch ($shape->type {
    case 'circle':
        return 'Circle';
    case 'square':
        return 'Square';
    case 'rect':
        return 'Rect';
}
