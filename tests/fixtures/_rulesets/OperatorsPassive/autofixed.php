<?php

declare(strict_types=1);

namespace App\Fixtures;

function everyPassiveOperator(int $number, object $model, array $items, string $dir): void
{
    $identity = +$number;
    $negation = -$number;
    $postIncrement = $number++;
    $preIncrement = ++$number;
    $postDecrement = $number--;
    $preDecrement = --$number;
    $suppressed = @file_get_contents('x');
    $executed = `ls`;
    $interpolated = `ls $dir`;
    $element = $items[0];
    $chainedElement = $items[0][1];
    $property = $model->name;
    $suppressedNegation = @-$number;
}
