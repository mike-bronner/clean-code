<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Pattern.ThrowOnlyMethodOverride.
 *
 * Every reported shape, one per method: the hierarchy declared by `extends`
 * alone, by `implements` alone, and by both; an anonymous class and an enum,
 * the two class-likes beside a named class that can declare a supertype; a
 * `throw` whose arguments carry a closure, so the body holds semicolons the
 * statement swallows; a body opening on a comment; and a re-throw of a
 * variable rather than a `new` expression.
 *
 * `RealStorage::read()` and `Refusing::size()` are implemented for real, so a
 * sniff that flagged every method of a subtype would not match this file.
 */

interface Reader
{
    public function read(): string;

    public function size(): int;
}

abstract class Storage
{
    abstract public function read(): string;
}

final class ExtendsOnly extends Storage
{
    public function read(): string
    {
        throw new BadMethodCallException('not supported');
    }
}

final class ImplementsOnly implements Reader
{
    public function read(): string
    {
        throw new BadMethodCallException('not supported');
    }

    public function size(): int
    {
        throw new BadMethodCallException('not supported');
    }
}

final class Refusing extends Storage implements Reader
{
    public function read(): string
    {
        // The supertype promises this; we do not.
        throw new BadMethodCallException('not supported');
    }

    public function size(): int
    {
        return 0;
    }
}

final class Rethrowing extends Storage
{
    public function __construct(private readonly Throwable $reason)
    {
    }

    public function read(): string
    {
        throw $this->reason;
    }
}

final class Reporting extends Storage
{
    public function read(): string
    {
        throw new BadMethodCallException(implode('', array_map(function (string $part): string {
            $trimmed = trim($part);

            return strtoupper($trimmed);
        }, ['not', 'supported'])));
    }
}

enum Encoding: string implements Reader
{
    case Utf8 = 'utf-8';

    public function read(): string
    {
        throw new BadMethodCallException('not supported');
    }

    public function size(): int
    {
        throw new BadMethodCallException('not supported');
    }
}

final class RealStorage extends Storage
{
    public function read(): string
    {
        return 'real';
    }

    public function anonymous(): Reader
    {
        return new class implements Reader {
            public function read(): string
            {
                throw new BadMethodCallException('not supported');
            }

            public function size(): int
            {
                return 0;
            }
        };
    }
}
