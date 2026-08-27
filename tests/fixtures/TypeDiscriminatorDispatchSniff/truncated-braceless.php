<?php

declare(strict_types=1);

/**
 * A brace-less chain cut off right after a condition, so no clause carries a
 * scope and the last clause's body walk starts past the last token in the file.
 */

if ($shape->type === 'circle')
    return 'Circle';
elseif ($shape->type === 'square')
