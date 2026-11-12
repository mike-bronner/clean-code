<?php

$granted = $isOwner || $isAdmin;
$a = $b || $c && $d;

$a = $b || $c;
$a += $b || $c;
$a -= $b || $c;
$a *= $b || $c;
$a /= $b || $c;
$a %= $b || $c;
$a **= $b || $c;
$a .= $b || $c;
$a ??= $b || $c;
$a &= $b || $c;
$a |= $b || $c;
$a ^= $b || $c;
$a <<= $b || $c;
$a >>= $b || $c;
$a = &$b || $c;

$a['k'] = $b || $c;
$a[] = $b || $c;
$this->p = $b || $c;
$o?->p?->q = $b || $c;
Foo::$p = $b || $c;
static::$p = $b || $c;
list($a, $b) = $c || $d;
[$a, $b] = $c || $d;
['x' => $a, 'y' => $b] = $c || $d;
[$a, [$b, $c]] = $d || $e;
$a = $b = $c || $d;

$handle = fopen($path, 'r') || die();
$handle = fopen($path, 'r') || exit();
$handle = fopen($path, 'r') || throw new RuntimeException();

$a = $b && $c;

$granted = ($isOwner or $isAdmin);
$a = ($b and $c);
$a = ($b xor $c);
$a = $b = ($c or $d);

function scopedSymbols(): void
{
    $x = $a || $b;
}

$f = function () {
    $x = $a || $b;
};

if ($c) {
    $x = $a || $b;
}

if ($c) $a = $b || $d;

if ($c):
    $x = $a || $b;
endif;

switch ($c) {
    case 1:
        $x = $a || $b;
        break;
    default:
        $x = $a || $b;
}

$granted = $isOwner
    || $isAdmin;

$granted = $isOwner
    // a reason
    || $isAdmin;

function staticScopedSymbols(): void
{
    static $x = true || false;

    $y = $a || $b;
}

usort($items, function ($left, $right) {
    $x = $a || $b;

    return 0;
});

$x = $o->or($a);
$x = Foo::AND;
$x = foo(and: 1);
$x = $order;
$x = 'this or that';
$x = "this and that";
$x = <<<TEXT
    this or that
    TEXT;
$x = 1; // this or that
/* $x = $a or $b; */

$x = foo($a or $b);
$x = foo($a, $b or $c);
$x = [$a or $b];
$x = array($a or $b);
$f = function () {
    return $a or $b;
};
$x = match (true) {
    default => $a or $b,
};
$f = fn () => $a or $b;
$f = fn () => $x = $a or $b;

foo() or bar();
$a == $b or $c;
$a === $b or $c;
$a and $b = foo();
($handle = fopen($path, 'r')) or throw new RuntimeException();
print $a = $b or $c;

if ($a = $b or $c) {
}

while ($a = $b or $c) {
}

for ($i = 0; $a = $b or $c; $i++) {
}

function returns(): bool
{
    return $a and $b;
}

echo $a = $b or $c;

const A = true or false;

class Declarations
{
    const B = true or false;

    public const C = true or false;

    public bool $p = true or false;

    public function run(): void
    {
        static $x = true or false;
    }
}

enum Suit: int
{
    case Hearts = 1 or 0;
}
?>
<?php $a = $b || $c ?>
<?= $a = $b or $c ?>
<?php
