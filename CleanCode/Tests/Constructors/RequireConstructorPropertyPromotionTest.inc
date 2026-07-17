<?php

// Positive: every property promoted — visibility modifiers, readonly, defaults.
class PromotedEverything
{
    public function __construct(
        private string $name,
        protected readonly int $count = 0,
        public ?array $options = null,
    ) {
    }
}

// Negative: property declared outside the constructor, assigned inside it.
class UnpromotedProperty
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

// Edge: promoted and non-promoted parameters mixed in one constructor.
class MixedPromotion
{
    protected int $count;

    public function __construct(private string $name, int $count)
    {
        $this->count = $count;
    }
}

// Edge: the property default value survives promotion.
class DefaultCarriedOver
{
    private array $options = [];

    public function __construct(array $options)
    {
        $this->options = $options;
    }
}
