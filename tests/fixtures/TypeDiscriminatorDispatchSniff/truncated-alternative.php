<?php

declare(strict_types=1);

/**
 * An alternative-syntax chain cut off at a clause's colon, so the unterminated
 * clause never receives the scope_closer this spelling uses both to bound the
 * body and to find the next clause.
 */

if ($shape->type === 'circle'):
    return 'Circle';
elseif ($shape->type === 'square'):
