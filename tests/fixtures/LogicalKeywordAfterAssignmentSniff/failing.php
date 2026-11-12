<?php

$granted = $isOwner or $isAdmin;
$a = $b or $c and $d;

$a = $b or $c;
$a += $b or $c;
$a -= $b or $c;
$a *= $b or $c;
$a /= $b or $c;
$a %= $b or $c;
$a **= $b or $c;
$a .= $b or $c;
$a ??= $b or $c;
$a &= $b or $c;
$a |= $b or $c;
$a ^= $b or $c;
$a <<= $b or $c;
$a >>= $b or $c;
$a = &$b or $c;

$a['k'] = $b or $c;
$a[] = $b or $c;
$this->p = $b or $c;
$o?->p?->q = $b or $c;
Foo::$p = $b or $c;
static::$p = $b or $c;
list($a, $b) = $c or $d;
[$a, $b] = $c or $d;
['x' => $a, 'y' => $b] = $c or $d;
[$a, [$b, $c]] = $d or $e;
$a = $b = $c or $d;

$handle = fopen($path, 'r') or die();
$handle = fopen($path, 'r') or exit();
$handle = fopen($path, 'r') or throw new RuntimeException();

$a = $b OR $c;
$a = $b And $c;
$a = $b XOR $c;
$a = $b xor $c;

function scoped(): void
{
    $x = $a or $b;
}

$f = function () {
    $x = $a or $b;
};

if ($c) {
    $x = $a or $b;
}

if ($c) $a = $b or $d;

if ($c):
    $x = $a or $b;
endif;

switch ($c) {
    case 1:
        $x = $a or $b;
        break;
    default:
        $x = $a or $b;
}

$granted = $isOwner
    or $isAdmin;

$granted = $isOwner
    // a reason
    or $isAdmin;

function staticScoped(): void
{
    static $x = true or false;

    $y = $a or $b;
}

usort($items, function ($left, $right) {
    $x = $a or $b;

    return 0;
});
?>
<?php $a = $b or $c ?>
<?php
