<?php

declare(strict_types=1);

/**
 * A qualifying `switch` cut off inside its body, so the tokenizer assigns it no
 * scope at all — neither the opener the arm walk starts from nor the closer it
 * stops at.
 *
 * Every other rule is satisfied: a one-hop discriminator read and three
 * scalar-literal `case` labels. The closing brace is the single thing missing,
 * which is what makes this the fixture that pins the scope check: a walk that
 * counted arms without first confirming the switch closed would report a
 * construct on a file PHP itself cannot parse.
 */

switch ($shape->type) {
    case 'circle':
        return 'Circle';
    case 'square':
        return 'Square';
    case 'rect':
        return 'Rect';
