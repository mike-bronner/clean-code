<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Classes.DisallowConstructorInstantiation.
 *
 * Two things keep this file discriminating:
 *
 *   1. The compliant form of the construct the sniff registers on — a
 *      constructor that *receives* its collaborators, promoted or assigned,
 *      and one that has no body statements at all.
 *   2. Every near-miss shape the sniff must stay silent on: `throw new` in a
 *      constructor, `new` in an ordinary method and in a named constructor,
 *      `new` in a constructor's parameter list (PHP 8.1 new-in-initializers),
 *      `new` inside a closure and an arrow function declared in a constructor
 *      body, a method whose name merely resembles `__construct`, and an
 *      abstract constructor with no body. Dropping any one of the sniff's
 *      guards reddens this file.
 */

final class Mailer
{
}

final class NullLogger
{
}

final class Queue
{
}

final class Digest
{
}

final class Injected
{
    public function __construct(
        private Mailer $mailer,
        private NullLogger $logger = new NullLogger(),
    ) {
    }
}

final class Assigned
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }
}

final class Empties
{
    public function __construct()
    {
    }
}

final class Guarded
{
    public function __construct(private int $retries)
    {
        if ($retries < 1) {
            throw new InvalidArgumentException('retries must be positive');
        }
    }
}

final class Deferred
{
    /** @var callable */
    private $makeMailer;

    /** @var callable */
    private $makeQueue;

    public function __construct()
    {
        $this->makeMailer = static function (): Mailer {
            return new Mailer();
        };
        $this->makeQueue = static fn (): Queue => new Queue();
    }
}

final class Factory
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public static function create(): self
    {
        return new self(new Mailer());
    }

    public function digest(): Digest
    {
        return new Digest();
    }

    public function __constructor(): Queue
    {
        return new Queue();
    }
}

abstract class Base
{
    abstract public function __construct(Mailer $mailer);
}
