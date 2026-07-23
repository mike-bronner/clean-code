<?php

declare(strict_types=1);

// A trailing `+`/`-` off a value the operand model must recognise: magic
// constants, interpolated double-quoted strings, and heredoc/nowdoc bodies all
// end a real left-hand operand, so the operator genuinely trails and is flagged
// (never mistaken for a unary sign).
$magic = __LINE__ -
    1;

$interpolated = "id-$id" -
    5;

$heredoc = <<<NUM
    41
    NUM -
    1;

$nowdoc = <<<'NUM'
    41
    NUM -
    1;
