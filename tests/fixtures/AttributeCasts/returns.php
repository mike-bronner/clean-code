<?php

namespace App\Concerns\Book;

use App\Support\Attribute as LocalAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Casts\Attribute as Cast;

trait Attributes
{
    public function imported(): Attribute
    {
    }

    public function aliased(): Cast
    {
    }

    public function fullyQualified(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
    }

    public function nullable(): ?Attribute
    {
    }

    public function otherAlias(): LocalAttribute
    {
    }

    public function otherFullyQualified(): \App\Support\Attribute
    {
    }

    public function untyped()
    {
    }
}
