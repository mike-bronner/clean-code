<?php

/**
 * Extends clauses written as qualified names, which is how a model is declared
 * in a file that imports nothing.
 *
 * findExtendedClassName() returns the parent exactly as written, so the gate
 * has to reduce `\Illuminate\Database\Eloquent\Relations\Pivot` to `Pivot`
 * before comparing. Reading it whole would close the gate on a real model.
 *
 * `QualifiedPivotModel` is the class that settles it: `Pivot` is a configured
 * parent name, and the qualified spelling matches nothing — neither the list
 * nor the "ends in Model" fallback — until the qualifiers come off.
 * `QualifiedEloquentModel` is the spelling the sniff's own docblock names; it
 * is here because that is the shape a reader will look for, but its parent ends
 * in "Model" either way, so its violation is not evidence on its own.
 *
 * `QualifiedController` is the other side of the gate: a qualified parent that
 * is not model-shaped stays out, so stripping qualifiers is not the same as
 * opening the gate on anything written with a separator in it.
 */

declare(strict_types=1);

class QualifiedPivotModel extends \Illuminate\Database\Eloquent\Relations\Pivot
{
    public $zulu;

    public $alpha;
}

class QualifiedEloquentModel extends \Illuminate\Database\Eloquent\Model
{
    public $zulu;

    public $alpha;
}

class QualifiedController extends \App\Http\Controllers\Controller
{
    public $zulu;

    public $alpha;
}
