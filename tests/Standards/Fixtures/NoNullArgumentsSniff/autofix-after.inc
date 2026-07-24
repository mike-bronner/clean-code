<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff;

final class Notifier
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
        $this->relay('body', flag: null);
        new Notifier(channel: null);
        new Notifier(channel: null, retries: null);
        new Notifier(channel: null, retries: 3);
        new Notifier(channel: null, retries: 3);

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

/**
 * Left alone: a subclass may override relay() and rename $flag, so this file
 * cannot prove which parameter name the call would need.
 */
class Extendable
{
    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public function run(): void
    {
        $this->relay('body', null);
    }
}

/**
 * Left alone: `self` names the class the trait is flattened into, not the
 * trait, and that class may declare its own relay() with a different parameter
 * name — so writing $flag into these calls would fatal at runtime.
 */
trait Copied
{
    public function __construct(?string $tag = null)
    {
    }

    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public function run(): void
    {
        self::relay('body', null);
        new self(null);
    }
}

function dispatch(string $to, ?int $retries = null): void
{
}

dispatch('a', retries: null);
