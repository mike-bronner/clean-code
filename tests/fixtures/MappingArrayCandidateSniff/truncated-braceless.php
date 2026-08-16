<?php

declare(strict_types=1);

/**
 * A brace-less chain cut off right after a condition, so the walk's
 * findEndOfStatement() call starts past the end of the file.
 */

if ($code === 'a')
    return 'Alpha';
elseif ($code === 'b')
