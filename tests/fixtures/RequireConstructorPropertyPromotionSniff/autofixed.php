<?php

// A private property declared in the class body and merely copied from a
// same-named constructor parameter — the fixer promotes it.
class UnpromotedProperty
{
    public function __construct(private string $name)
    {
    }
}

// Promoted and non-promoted parameters mixed in one constructor — only the
// non-promoted property is flagged.
class MixedPromotion
{
    public function __construct(private string $name, protected int $count)
    {
    }
}

// The property's default value is carried onto the promoted parameter.
class DefaultCarriedOver
{
    public function __construct(private array $options = [])
    {
    }
}

// A public property — the fixer must carry the public modifier onto the
// promoted parameter.
class PublicPromotion
{
    public function __construct(public string $label)
    {
    }
}

// A readonly property — the fixer must carry the readonly modifier onto the
// promoted parameter.
class ReadonlyPromotion
{
    public function __construct(private readonly int $id)
    {
    }
}
