<?php

declare(strict_types=1);

namespace App\Fixtures;

// Everything the sniff must leave alone. Each declaration sits *on* a
// boundary rather than comfortably inside it, so silence here is
// discriminating: move one signature across the line any of them pins and the
// same file reports.

// 5 signatures — exactly the shipped maximum. The threshold is exclusive, so
// this is the compliant edge of the boundary and failing.php is the other.
interface ExactlyAtTheMaximum
{
    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;
}

// The same 5 signatures, surrounded by everything an interface body may hold
// that is not one: three parent interfaces in the `extends` list, two
// constants, and two PHP 8.4 property hooks. Count any single one of those
// seven and this interface reaches 6 and reports.
interface SurroundedByNonMethods extends AlphaContract, BetaContract, GammaContract
{
    public const LIMIT = 10;

    public const OFFSET = 2;

    public string $label { get; set; }

    public int $size { get; }

    public function first(): void;

    public function second(): void;

    public function third(): void;

    public function fourth(): void;

    public function fifth(): void;
}

// The four class-like constructs PHPCS gives their own tokens, each holding 6
// methods — one past the shipped maximum. None is an interface, so none is
// this sniff's subject; register T_CLASS, T_TRAIT, T_ENUM or T_ANON_CLASS
// beside T_INTERFACE and this file reports four times. Method counts for a
// class are CleanCode.CodeSize.TooManyMethods (#80) and
// CleanCode.Classes.TooManyPublicMethods (#83), at their own far higher caps.
class WideClass
{
    public function first(): void {}

    public function second(): void {}

    public function third(): void {}

    public function fourth(): void {}

    public function fifth(): void {}

    public function sixth(): void {}
}

trait WideTrait
{
    public function first(): void {}

    public function second(): void {}

    public function third(): void {}

    public function fourth(): void {}

    public function fifth(): void {}

    public function sixth(): void {}
}

enum WideEnum: string
{
    case Alpha = 'alpha';

    public function first(): void {}

    public function second(): void {}

    public function third(): void {}

    public function fourth(): void {}

    public function fifth(): void {}

    public function sixth(): void {}
}

$wideAnonymousClass = new class {
    public function first(): void {}

    public function second(): void {}

    public function third(): void {}

    public function fourth(): void {}

    public function fifth(): void {}

    public function sixth(): void {}
};
