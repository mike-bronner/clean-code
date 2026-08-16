<?php

declare(strict_types=1);

$a = true;
$b = false;
$c = true;

// The five operators this sniff owns, each correctly spaced.
$symbolAnd = $a && $b;
$symbolOr = $a || $b;
$wordAnd = $a and $b;
$wordOr = $a or $b;
$wordXor = $a xor $b;

// Chained, and mixed symbol/word forms in one expression.
$chained = $a && $b && $c;
$mixed = $a && $b || $c;
$wordChained = $a and $b or $c;

// A newline is valid separation, so both placements stay silent: the operator
// trailing its left operand, and the operator leading its right one. Which of
// the two a wrapped expression should use belongs to OperatorLineBreak and
// OneConditionPerLine, not here. These two cases are what ignoreNewlines
// suppresses — it has a separate branch for the space before the operator and
// the space after it, so both are exercised.
$trailing = $a &&
    $b;

$leading = $a
    && $b;

$wordTrailing = $a and
    $b;

$wordLeading = $a
    and $b;

// Near-miss operators this sniff must stay silent on: every one of these is
// badly spaced on purpose, and every one is owned by a different sniff. If
// this sniff ever re-registers their tokens, these lines start reporting and
// this fixture fails.
$assignment=$a;
$compound = $a;
$compound.='x';
$concatenation = 'x'.'y';
$negation = !$a;
$identity = $a===$b;
$arithmetic = 1+2;
$bitwiseAnd = 1&2;
$bitwiseOr = 1|2;
$comparison = 1<2;
