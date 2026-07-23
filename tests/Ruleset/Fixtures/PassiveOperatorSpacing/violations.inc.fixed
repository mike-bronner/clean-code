<?php

declare(strict_types=1);

namespace App\Fixtures;

function passiveOperators(int $a, object $obj, array $arr): void
{
    $identity = +$a;
    $negation = -$a;
    $postIncrement = $a++;
    $preIncrement = ++$a;
    $postDecrement = $a--;
    $preDecrement = --$a;
    $suppressed = @file_get_contents('x');
    $executed = `ls`;
    $element = $arr[0];
    $chainedElement = $arr[0][1];
    $property = $obj->name;
    $chainedProperty = $obj->name->length;
    $binaryPlus = $a + $a;
    $binaryMinus = $a - $a;
}
