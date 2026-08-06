<?php

interface Formats
{
    public function format(string $value): string;
}

abstract class Shape
{
    abstract public function area(): float;
}

class Circle extends Shape implements Formats
{
    private ?string $label = null;

    protected int|string $key = 0;

    private array $entries = [];

    public function __construct(private readonly float $radius, protected bool $filled = false)
    {
    }

    public function area(): float
    {
        return M_PI * $this->radius ** 2;
    }

    public function format(string $value): string
    {
        return trim($value);
    }

    public function combine(Countable&ArrayAccess $bag): void
    {
        $bag->offsetExists($this->label);
    }

    public function push(string ...$labels): int
    {
        return count($labels);
    }

    public function find(?int $id): ?self
    {
        return $id === null ? null : $this;
    }

    public function merge(array $items, iterable $extra): array
    {
        return [...$this->entries, ...$items, ...$extra];
    }

    /**
     * @return never
     */
    public function halt(): void
    {
        throw new RuntimeException('halt');
    }
}
