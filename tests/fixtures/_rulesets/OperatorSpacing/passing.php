<?php

declare(strict_types=1);

$a = 1 + 2;
$b = $a === 1;
$c = $a . 'x';
$d = $a - $b;
$e = $a * $b;

// Boundary exclusions. Each is written *without* the surrounding spaces the
// standard demands of a registered operator, so the line only stays silent
// while the construct really is excluded. Written conventionally, it would
// pass whether or not the sniff registers the token, and guard nothing.
$f = match ($a) {
    3=>'three',
    default=>'other',
};
$g=& $a;

function operatorSpacingDefaultValue(int $h=1): int
{
    return $h;
}

function operatorSpacingByReference(int &$i): void
{
    $i = 1;
}
