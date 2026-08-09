<?php

/**
 * Several properties declared from one statement. The standard orders
 * properties, not statements, so both names on the second line are checked and
 * the second of them is the one out of order.
 */

declare(strict_types=1);

class MultiPropertyModel extends Model
{
    public $alpha;

    public $delta, $bravo;
}
