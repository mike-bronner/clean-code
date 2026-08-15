<?php

declare(strict_types=1);

// Deliberately unbalanced. A file being edited can hold a closer whose opener
// was never typed, and such a closer carries no `bracket_opener`. It closes
// nothing, so it cannot enclose the chain either: the walk outward steps over
// it and the reads on both sides of it are still reported.
//
// A stray curly brace is the one that has to be stepped over rather than
// merely ignored. The walk asks a curly brace whether it opens a dynamic
// member name, which reads back from its opener -- so taking a closer with no
// opener for an enclosing construct aborts the whole run, and every remaining
// violation in the file is lost with it.
$squareBefore = $payload['before'];
]
$squareAfter = $payload['after'];

$parenBefore = $payload['before'];
)
$parenAfter = $payload['after'];

$curlyBefore = $payload['before'];
}
$curlyAfter = $payload['after'];
