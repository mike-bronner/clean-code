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
    public function __construct(private ?Mailer $mailer = null)
    {
    }

    public function relay(string $body, ?bool $flag = null): void
    {
    }

    public function run(): void
    {
        // Named arguments — the compliant way to skip an optional parameter.
        $this->relay('body', flag: null);
        Mailer::make(name: null);
        new Notifier(mailer: null);

        // Positional arguments that are not null.
        $this->relay('body', true);
        $this->relay('body');
        Mailer::make('digest');

        // null into a *required* parameter is legitimate — nothing to skip.
        // The anonymous class below declares its own optional-parameter
        // demand(); the sniff must resolve against Notifier's, not that one.
        $this->demand(null);

        $handler = new class () {
            public function demand(?string $value = null): void
            {
            }
        };

        // null nested inside a larger argument is part of an expression, not
        // a skipped parameter.
        $this->relay('body', [null] === [] ? true : false);
        $this->accept([null]);
        $this->accept([1 => null]);
        $this->relay('body', $this->coalesce() ?? null);

        // An argument that *begins* with null but continues into a larger
        // expression is an expression too — only a bare null is a skipped
        // parameter.
        $this->relay('body', null !== $this->coalesce());
        $this->relay('body', null === $this->coalesce() ? true : false);

        // A closure/callback argument whose body mentions null.
        $this->apply(static fn (): ?string => null);
        $this->apply(function (): ?string {
            return null;
        });

        // null outside any call-argument position.
        $value = null;

        if ($value === null) {
            $value = null;
        }

        $map = ['key' => null];
        $this->mailer = null;
    }

    public function forward(self $other): void
    {
        // Unresolvable: a call on any object other than $this could target a
        // different class that merely shares the method name, so the sniff
        // does not borrow this class's signature to judge it.
        $other->relay('body', null);
    }

    public function demand(?string $value): void
    {
    }

    public function accept(array $values): void
    {
    }

    public function apply(callable $callback): void
    {
    }

    public function coalesce(): ?bool
    {
        return null;
    }
}

function dispatch(string $to, ?int $retries = null): void
{
}

function demandTarget(?string $to): void
{
}

dispatch('a', retries: null);
dispatch('a', 3);
dispatch('a');
demandTarget(null);

// An unresolvable callee is left alone rather than guessed at: neither the
// declaration nor the parameter name is knowable from this file.
$mailer = new Mailer();
$mailer->send('body', null);
Unknown::make(null);
\dispatch('a', null);
