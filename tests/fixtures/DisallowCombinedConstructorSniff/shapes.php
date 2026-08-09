<?php

declare(strict_types=1);

/**
 * Variant-shape fixture for CleanCode.Constructors.DisallowCombinedConstructor.
 *
 * failing.php proves each signal fires at all. This file is the guard against a
 * token walk that only ever handled the one spelling it was written against —
 * every branching form the signals can sit in, every declaration form a
 * constructor can take, the argument readers in the places a body can put them,
 * every way a parameter can qualify as a mode flag, and the predicates that
 * take a second argument alongside the value they test.
 */

final class Mailer
{
}

final class NullLogger
{
}

/**
 * Every branching form a mode flag can head: `if`, `elseif`, a spaced
 * `else if`, `switch`, `match` (as the subject and as an arm condition), a
 * ternary, a brace-less `if`, and the alternative syntax.
 */
final class ModeFlagShapes
{
    public function __construct(bool $queued, bool $eager, bool $lazy)
    {
        if ($queued) {
            $this->transport = new Mailer();
        } elseif ($eager) {
            $this->transport = new NullLogger();
        } else if ($lazy) {
            $this->transport = new Mailer();
        }

        switch ($queued) {
            case true:
                $this->mode = 'queued';
                break;
            default:
                $this->mode = 'direct';
        }

        $this->kind = match ($eager) {
            true => new Mailer(),
            false => new NullLogger(),
        };

        $this->flavour = match (true) {
            $lazy => new NullLogger(),
            default => new Mailer(),
        };

        $this->pick = $queued ? new Mailer() : new NullLogger();

        if ($eager) $this->warm = new Mailer();

        if ($lazy):
            $this->cold = new NullLogger();
        endif;
    }
}

/**
 * Every form of type test: `instanceof`, each predicate spelling, and a
 * predicate reached through a ternary and a `match` arm rather than an `if`.
 */
final class TypeSwitchShapes
{
    public function __construct(mixed $source, object $handler, mixed $extra)
    {
        if ($handler instanceof Mailer) {
            $this->handler = $handler;
        } else {
            $this->handler = new NullLogger();
        }

        if (is_array($source)) {
            $this->lines = $source;
        } elseif (is_callable($source)) {
            $this->lines = [$source];
        }

        if (gettype($extra) === 'integer') {
            $this->extra = [$extra];
        } else {
            $this->extra = [];
        }

        $this->tag = is_string($source) ? 'text' : 'other';

        $this->shape = match (true) {
            is_object($extra) => new Mailer(),
            default => new NullLogger(),
        };

        switch (true) {
            case is_iterable($extra):
                $this->extra = [...$extra];
                break;
            default:
                $this->extra = [];
        }
    }
}

/**
 * The argument readers do not need a branch to be a mode signal, so they are
 * reported wherever they appear in the body — including inside a nested
 * conditional and inside a `foreach`.
 */
final class ArgumentCountShapes
{
    public function __construct()
    {
        if (func_num_args() > 1) {
            $this->arguments = func_get_args();
        }

        foreach (FUNC_GET_ARGS() as $argument) {
            $this->tail[] = $argument;
        }
    }
}

/**
 * Constructors that are not a plain class's: a trait's, an anonymous class's,
 * and one spelled `__CONSTRUCT`, since PHP method names are case-insensitive.
 * The promoted parameter proves promotion does not hide a flag from the body
 * scan.
 */
trait BuildsItself
{
    public function __construct(private bool $queued)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();
    }
}

final class ShoutsItsName
{
    public function __CONSTRUCT(bool $queued)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();
    }
}

final class HoldsAnonymousClass
{
    public function build(): object
    {
        return new class (true) {
            public function __construct(bool $queued)
            {
                $this->transport = $queued ? new Mailer() : new NullLogger();
            }
        };
    }
}

/**
 * A flag declared without the `bool` type, carrying a `true`/`false` default
 * instead. This is the second leg of the flag test — an untyped parameter and a
 * `mixed`-typed one reach it and nothing else does, so a `bool`-typed parameter
 * cannot stand in for either.
 */
final class DefaultedModeFlags
{
    public function __construct($queued = false, mixed $eager = true)
    {
        $this->transport = $queued ? new Mailer() : new NullLogger();

        if ($eager) {
            $this->warm = new Mailer();
        }
    }
}

/**
 * The two predicates that take more than one argument. Only the first argument
 * is the subject being type-tested, so `$source` is a signal and the
 * `$expectedClass` it is compared against — a value the call reads, never a
 * parameter whose own type is switched on — is not.
 */
final class MultiArgumentPredicates
{
    public function __construct(mixed $source, string $expectedClass)
    {
        if (is_a($source, $expectedClass)) {
            $this->handler = $source;
        } else {
            $this->handler = new NullLogger();
        }

        $this->child = is_subclass_of($source, $expectedClass) ? new Mailer() : new NullLogger();
    }
}
