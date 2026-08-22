<?php

/**
 * Property declarations whose only declaration-start marker is a non-visibility
 * modifier — `final`, `readonly`, `static`, `var`.
 *
 * The property walk decides "is this variable a member declaration or a local
 * inside a hook body" by looking at the first token of the statement holding it.
 * A visibility keyword answers that, and `public readonly string $x;` is
 * answered by the `public` alone — so a fixture written that way says nothing
 * about the four keywords the sniff also accepts. Each class below leads its
 * out-of-order property with one of them and nothing else, which is what makes
 * the four independently reachable.
 *
 * One class per keyword: sharing one would let the first unrecognised property
 * be skipped and its baseline carry, so a second keyword's disorder could go
 * unreported for the wrong reason.
 */

declare(strict_types=1);

class FinalPropertyModel extends Model
{
    public $zulu;

    final string $alpha = '';
}

class ReadonlyPropertyModel extends Model
{
    public $zulu;

    readonly string $alpha;
}

class StaticPropertyModel extends Model
{
    public $zulu;

    static $alpha;
}

class VarPropertyModel extends Model
{
    public $zulu;

    var $alpha;
}
