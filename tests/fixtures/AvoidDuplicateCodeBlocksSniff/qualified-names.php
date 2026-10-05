<?php

declare(strict_types=1);

// PHP_CodeSniffer 4 reads each qualified name as one token, and the sniff
// compares that token's content as written. Only the first and the last block
// repeat, apart from their variable names. A relative name keeps its
// `namespace` keyword apart from a qualified name, and a rooted name keeps its
// leading separator.

$a = namespace\Alpha::make();
$b = namespace\Alpha::make($a);
$c = namespace\Alpha::make($a, $b);
$d = namespace\Alpha::make($a, $b, $c);
$e = namespace\Alpha::make($a, $b, $c, $d);

$f = Sub\Alpha::make();
$g = Sub\Alpha::make($f);
$h = Sub\Alpha::make($f, $g);
$i = Sub\Alpha::make($f, $g, $h);
$j = Sub\Alpha::make($f, $g, $h, $i);

$k = \Sub\Alpha::make();
$l = \Sub\Alpha::make($k);
$m = \Sub\Alpha::make($k, $l);
$n = \Sub\Alpha::make($k, $l, $m);
$o = \Sub\Alpha::make($k, $l, $m, $n);

$p = namespace\Alpha::make();
$q = namespace\Alpha::make($p);
$r = namespace\Alpha::make($p, $q);
$s = namespace\Alpha::make($p, $q, $r);
$t = namespace\Alpha::make($p, $q, $r, $s);
