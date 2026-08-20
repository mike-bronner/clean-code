<?php

declare(strict_types=1);

/**
 * A brace-less chain cut off right after a condition, so no clause carries a
 * scope and the walk's findEndOfStatement() call starts past the last token in
 * the file.
 */

if ($shape->type === 'circle')
    return 'Circle';
elseif ($shape->type === 'square')
