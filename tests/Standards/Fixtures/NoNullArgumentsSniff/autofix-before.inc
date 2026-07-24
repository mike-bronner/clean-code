<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff;

class Notifier
{
    public function __construct(?string $channel = null, ?int $retries = null)
    {
    }

    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public function collect(?string $first = null, ...$rest): void
    {
    }

    public function run(): void
    {
        $this->relay('body', null);
        new Notifier(null);
        new Notifier(null, null);
        new Notifier(null, 3);
        new Notifier(null, retries: 3);

        // Left alone: the trailing spread cannot be named.
        $this->collect(null, ...$this->rest());

        // Left alone: already compliant.
        $this->relay('body', flag: null);
    }

    public function rest(): array
    {
        return [];
    }
}

function dispatch(string $to, ?int $retries = null): void
{
}

dispatch('a', null);
