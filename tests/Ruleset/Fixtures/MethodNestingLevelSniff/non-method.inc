<?php

declare(strict_types=1);

// Top-level script code is out of scope: the standard governs method bodies.
// This nesting would be level 3 inside a method, but here it must not be
// flagged because no function encloses it.
if (isset($GLOBALS['x'])) {
    foreach ($GLOBALS['items'] as $item) {
        while ($item > 0) {
            $item--;
        }
    }
}
