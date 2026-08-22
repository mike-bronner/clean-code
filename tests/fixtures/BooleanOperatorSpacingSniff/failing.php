<?php

declare(strict_types=1);

$a = true;
$b = false;

// Each operator gets all four defect shapes: no space before, no space after,
// no space on either side, and over-padding on both sides.
//
// The word operators are parenthesised wherever the space before them is
// removed, because `$a and $b` with the leading space deleted would read as
// the single identifier `$aand`; `($a)and $b` still tokenises as T_LOGICAL_AND
// and so actually reaches the sniff, which is the point of the case.

$symbolAndNoBefore = $a&& $b;
$symbolAndNoAfter = $a &&$b;
$symbolAndNeither = $a&&$b;
$symbolAndPadded = $a  &&  $b;

$symbolOrNoBefore = $a|| $b;
$symbolOrNoAfter = $a ||$b;
$symbolOrNeither = $a||$b;
$symbolOrPadded = $a  ||  $b;

$wordAndNoBefore = ($a)and $b;
$wordAndNoAfter = $a and$b;
$wordAndNeither = ($a)and$b;
$wordAndPadded = $a  and  $b;

$wordOrNoBefore = ($a)or $b;
$wordOrNoAfter = $a or$b;
$wordOrNeither = ($a)or$b;
$wordOrPadded = $a  or  $b;

$wordXorNoBefore = ($a)xor $b;
$wordXorNoAfter = $a xor$b;
$wordXorNeither = ($a)xor$b;
$wordXorPadded = $a  xor  $b;
