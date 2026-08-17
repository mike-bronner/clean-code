<?php

/**
 * Several properties declared from one statement, and a property whose
 * declaration is preceded by attributes. Both are shapes of the same question —
 * where a property declaration starts — which the property walk has to answer
 * to tell a member from a hook body's locals.
 *
 * The standard orders properties, not statements, so both names on the
 * `$delta, $bravo` line are checked and the second of them is the one out of
 * order.
 */

declare(strict_types=1);

class MultiPropertyModel extends Model
{
    public $alpha;

    public $delta, $bravo;
}

class AttributedPropertyModel extends Model
{
    #[Encrypted]
    public string $zulu = '';

    #[Encrypted]
    #[Cast(AsCollection::class)]
    public string $alpha = '';
}
