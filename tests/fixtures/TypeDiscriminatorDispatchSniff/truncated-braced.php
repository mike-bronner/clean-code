<?php

declare(strict_types=1);

/**
 * A braced chain cut off mid-`else`, so the walk's "find the next clause after
 * the closing brace" step runs past the last token in the file.
 *
 * The two clauses before the cut share one discriminator read and compare it
 * against scalar literals, so only the unfinished third branch stands between
 * this file and a report.
 */

if ($shape->type === 'circle') {
    return 'Circle';
} elseif ($shape->type === 'square') {
    return 'Square';
} else
