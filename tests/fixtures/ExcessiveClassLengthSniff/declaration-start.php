<?php

declare(strict_types=1);

namespace App\Fixtures;

// PDepend takes a class's start line from the first of its
// `abstract`/`final`/`readonly` modifiers, not from the `class` keyword and not
// from a doc block or attribute above it. That line is both where PHPMD reports
// the violation and where the length starts counting.

/**
 * A doc block above the class is not part of the class.
 */
#[Deprecated]
abstract class Alpha
{
    abstract public function run(): void;
}

final class Beta
{
    public function run(): void
    {
    }
}

readonly class Gamma
{
    public function __construct(public int $id)
    {
    }
}

abstract
class Delta
{
    abstract public function run(): void;
}
