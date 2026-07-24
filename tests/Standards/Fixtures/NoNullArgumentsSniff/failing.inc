<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff;

class Mailer
{
    public function send(string $body, ?string $subject = null, ?array $cc = null): void
    {
    }

    public static function make(?string $name = null): self
    {
        return new self();
    }
}

class Notifier
{
    public function __construct(?Mailer $mailer = null, ?string $channel = null)
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
        // Method call on $this, plain and nullsafe.
        $this->relay('body', null);
        $this?->relay('body', null);

        // Static call on a class declared in this file.
        Mailer::make(null);
        self::helper(null);

        // Constructor calls.
        new Notifier(null);
        new self(null);

        // Multiple null arguments in a single call.
        new Notifier(null, null);

        // A null that skips an optional parameter positioned before further
        // positional arguments.
        new Notifier(null, 'sms');

        // Not auto-fixable: the argument after the flagged null is unpacked
        // from a spread, so it cannot be given a name — whether it lands in a
        // variadic parameter...
        $this->collect(null, ...$this->rest());

        // ...or in ordinary ones.
        $this->pair(null, ...$this->rest());

        // Not auto-fixable either: the argument after the flagged null lands
        // in a variadic parameter, which a named argument cannot address.
        $this->collect(null, 'extra');
    }

    public function pair(?string $left = null, ?string $right = null): void
    {
    }

    public static function helper(?int $level = null): void
    {
    }

    public function rest(): array
    {
        return [];
    }
}

function dispatch(string $to, ?int $retries = null): void
{
}

// Standalone function call.
dispatch('a', null);
