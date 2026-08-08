<?php

declare(strict_types=1);

/**
 * Variant-shape fixture for CleanCode.Classes.DisallowConstructorInstantiation.
 *
 * failing.php covers the plain shape: a `new` sitting directly among a
 * constructor's statements. This file is the guard against a token walk that
 * only ever handled that one shape — every constructor below is written in a
 * different declaration form, or holds its `new` somewhere other than the top
 * level of the body.
 *
 * The nested-scope cases are the load-bearing pair: a scope that is ordinary
 * constructor code (an `if`, a `foreach`) must still be walked, while a scope
 * that is a *declaration* (closure, arrow function, anonymous class body) must
 * be jumped over. Skipping the first, or walking into the second, changes the
 * expected list in tests/Standards/DisallowConstructorInstantiationTest.php.
 */

final class Mailer
{
}

final class Queue
{
}

final class Digest
{
}

trait Notifies
{
    private Mailer $mailer;

    public function __construct()
    {
        $this->mailer = new Mailer();
    }
}

final class Uppercased
{
    private Mailer $mailer;

    public function __CONSTRUCT()
    {
        $this->mailer = new Mailer();
    }
}

final class Nested
{
    private ?Mailer $mailer = null;

    /** @var array<int, Queue> */
    private array $queues = [];

    public function __construct(bool $withMailer, int $queueCount)
    {
        if ($withMailer) {
            $this->mailer = new Mailer();
        }

        foreach (range(1, $queueCount) as $ignored) {
            $this->queues[] = new Queue();
        }
    }
}

final class Wrapped
{
    private Digest $digest;

    public function __construct()
    {
        $this->digest = new Digest(new Mailer(), new Queue());
    }
}

final class Anonymous
{
    private object $worker;

    public function __construct()
    {
        $this->worker = new class {
            public function make(): Mailer
            {
                return new Mailer();
            }
        };
    }
}

final class Rethrows
{
    private Queue $queue;

    public function __construct(?Queue $queue)
    {
        if ($queue === null) {
            throw new RuntimeException('a queue is required');
        }

        $this->queue = $queue;
    }
}

interface Bodyless
{
    public function __construct(Mailer $mailer);
}

/**
 * The far edge of the `throw` exemption. In both constructors below the throw
 * is an *operand* of a larger expression, so its exemption must end where the
 * thrown expression does — the sibling `new Mailer()` is ordinary constructor
 * code and is still reported.
 */
final class ThrowsMidExpression
{
    private Mailer $mailer;

    public function __construct(bool $flag)
    {
        $this->mailer = $flag
            ? throw new RuntimeException('no mailer for you')
            : new Mailer();
    }
}

final class ThrowsInMatchArm
{
    private Mailer $mailer;

    public function __construct(int $mode)
    {
        $this->mailer = match ($mode) {
            0 => throw new RuntimeException('unsupported'),
            default => new Mailer(),
        };
    }
}

final class ThrowsCoalesced
{
    private Mailer $mailer;

    public function __construct(?Mailer $mailer, bool $required)
    {
        $this->mailer = $mailer ?? ($required
            ? throw new RuntimeException('a mailer is required')
            : new Mailer());
    }
}
