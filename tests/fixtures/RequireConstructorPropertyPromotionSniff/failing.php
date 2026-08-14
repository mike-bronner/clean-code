<?php

// A private property declared in the class body and merely copied from a
// same-named constructor parameter — the fixer promotes it.
class UnpromotedProperty
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

// Promoted and non-promoted parameters mixed in one constructor — only the
// non-promoted property is flagged.
class MixedPromotion
{
    protected int $count;

    public function __construct(private string $name, int $count)
    {
        $this->count = $count;
    }
}

// The property's default value is carried onto the promoted parameter.
class DefaultCarriedOver
{
    private array $options = [];

    public function __construct(array $options)
    {
        $this->options = $options;
    }
}

// A public property — the fixer must carry the public modifier onto the
// promoted parameter.
class PublicPromotion
{
    public string $label;

    public function __construct(string $label)
    {
        $this->label = $label;
    }
}

// A readonly property — the fixer must carry the readonly modifier onto the
// promoted parameter.
class ReadonlyPromotion
{
    private readonly int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }
}
