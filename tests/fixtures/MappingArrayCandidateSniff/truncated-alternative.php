<?php

declare(strict_types=1);

/**
 * An alternative-syntax chain cut off at a clause's colon, so the unterminated
 * clause never receives a scope_closer — the pointer the walk uses both to
 * bound the body and to find the next clause.
 */

if ($code === 'a'):
    return 'Alpha';
elseif ($code === 'b'):
