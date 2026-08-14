<?php

declare(strict_types=1);

/**
 * A braced chain cut off mid-`else`, so the walk's "find the next clause after
 * the closing brace" step runs past the last token in the file.
 *
 * PHP_CodeSniffer tokenizes a truncated file rather than refusing it, so the
 * sniff is handed one. It must terminate and stay silent, never stall on a
 * clause it cannot finish reading.
 */

if ($code === 'a') {
    return 'Alpha';
} elseif ($code === 'b') {
    return 'Bravo';
} else
