<?php

class FullyUsed
{
    private string $used = 'value';

    private static int $counter = 0;

    public function run(): string
    {
        static::$counter++;

        return $this->used . $this->helper() . self::staticHelper();
    }

    private function helper(): string
    {
        return 'helper';
    }

    private static function staticHelper(): string
    {
        return 'static-helper';
    }
}

class HasDeadCode
{
    private string $unusedProperty = 'dead';

    private int $usedProperty = 1;

    public function run(): int
    {
        return $this->usedProperty;
    }

    private function unusedMethod(): void
    {
    }
}

class EdgeCases
{
    private string $viaString = 'irrelevant';

    protected string $notPrivate = 'visible';

    private function __construct()
    {
    }

    public static function make(): self
    {
        return new self();
    }

    public function run(): array
    {
        return [[$this, 'viaCallable'], 'viaString', $this->notPrivate];
    }

    public function unusedPublic(): void
    {
    }

    private function viaCallable(): void
    {
    }
}

class PromotedProperties
{
    public function __construct(private int $promoted)
    {
    }
}

abstract class NoBody
{
    abstract public function contract(): void;
}
