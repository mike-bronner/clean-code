<?php

declare(strict_types=1);

/**
 * Compliant fixture for CleanCode.Pattern.ThrowOnlyMethodOverride.
 *
 * Two things keep this file discriminating:
 *
 *   1. The shapes a hierarchy is *allowed* to carry: an inherited method
 *      implemented for real, a `throw` reached through a guard clause, a
 *      `throw` that is not the whole body in either direction, and an
 *      inherited method left abstract rather than stubbed out.
 *   2. Every near miss the sniff must stay silent on: a throw-only method in a
 *      type that declares no supertype — a plain class, a bare enum, an
 *      anonymous class, a trait, a nested function, a function at file scope —
 *      plus an interface signature, an empty body, and `return throw ...`,
 *      which hands the caller a value instead of refusing one.
 *
 * Which of these redden this file on their own is set out beside the
 * compliant-fixture test in tests/Standards/ThrowOnlyMethodOverrideTest.php.
 */

interface Reader
{
    public function read(): string;
}

interface SeekableReader extends Reader
{
    public function seek(int $offset): void;
}

abstract class Storage
{
    abstract public function read(): string;

    public function unsupported(): string
    {
        throw new BadMethodCallException('no supertype promises this');
    }
}

final class FileStorage extends Storage implements SeekableReader
{
    public function read(): string
    {
        return file_get_contents('/dev/null');
    }

    public function seek(int $offset): void
    {
        if ($offset >= 0) {
            return;
        }

        throw new InvalidArgumentException('negative offset');
    }

    public function rewind(): void
    {
        $this->seek(0);

        throw new LogicException('unreachable');
    }

    public function migrate(): void
    {
        throw new RuntimeException('not yet');

        $this->rewind();
    }

    public function nothing(): void
    {
    }

    public function orFail(): string
    {
        return throw new RuntimeException('handed to the caller');
    }
}

abstract class NullStorage extends Storage
{
    abstract public function read(): string;
}

enum Format
{
    case Json;

    public function read(): string
    {
        throw new BadMethodCallException('a bare enum declares no supertype');
    }
}

trait Refusing
{
    public function read(): string
    {
        throw new BadMethodCallException('a trait declares no supertype');
    }
}

final class Outer implements Reader
{
    public function read(): string
    {
        function nestedRefusal(): string
        {
            throw new BadMethodCallException('a nested function overrides nothing');
        }

        return nestedRefusal();
    }

    public function anonymous(): object
    {
        return new class {
            public function read(): string
            {
                throw new BadMethodCallException('an anonymous class with no supertype');
            }
        };
    }
}

function freeRefusal(): string
{
    throw new BadMethodCallException('file scope has no enclosing type');
}
