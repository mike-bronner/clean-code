<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Standards\Fixtures\NoNullArgumentsSniff;

/**
 * Declared before the namespace-level functions below so a name lookup that
 * forgot interfaces are class-like scopes would reach this method first. An
 * interface method is not a namespace-level function and must never be matched
 * as one — demandTarget() is the function that call resolves to, and its
 * parameter is required.
 */
interface Recipient
{
    public function demandTarget(?string $to = null): void;
}

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

final class Notifier
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

        // PHP variable names are case-sensitive, so $This is an ordinary
        // variable holding who-knows-what — not the $this this class knows.
        $This = $other;
        $This->relay('body', null);
    }

    public function callDynamically(callable $callback): void
    {
        // The callee is not a name the file can look up.
        $callback('body', null);
        ($this->coalesce(...))('body', null);
    }

    public function inherit(): void
    {
        // Declared by a parent class in another file, so its parameters are
        // not knowable here.
        $this->inherited('body', null);
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

class Forwarder extends Mailer
{
    public function run(): void
    {
        // `parent::` is deliberately not resolved: the parent is normally
        // declared in another file, and the sniff makes no exception when it
        // happens to be local.
        parent::send('body', null);
    }
}

// An attribute whose class the file does not declare stays unresolved, and so
// does a second attribute in a group.
#[Unknown('/users', null)]
#[Unknown('/x', null), AlsoUnknown('/y', null)]
class Controller
{
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
Vendor\Thing::make(null);

// A qualified name refers outside this file even when its short name matches a
// class the file declares — Vendor\Mailer is not this namespace's Mailer.
Vendor\Mailer::make(null);
