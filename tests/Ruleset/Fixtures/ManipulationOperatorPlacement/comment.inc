<?php

declare(strict_types=1);

// A comment between the operands would be reordered by the fix, so the
// violation is reported but left for the developer to resolve.
$result = 4 + // trailing note
    4;
