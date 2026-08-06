<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures\UndefinedVariable;

use Throwable;

class Compliant
{
    private string $name = 'compliant';

    public function assigned(): string
    {
        $greeting = 'hello';

        return $greeting;
    }

    public function parameter(string $subject): string
    {
        return $subject;
    }

    public function property(): string
    {
        return $this->name;
    }

    public function loop(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += $row;
        }

        return $total;
    }

    public function caught(): string
    {
        try {
            $result = $this->assigned();
        } catch (Throwable $error) {
            $result = $error->getMessage();
        }

        return $result;
    }

    public function superglobal(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function inherited(int $factor): callable
    {
        return function (int $value) use ($factor): int {
            return $value * $factor;
        };
    }

    public function byReference(array &$bucket): void
    {
        $bucket[] = 1;
    }

    public function destructured(array $pair): int
    {
        [$left, $right] = $pair;

        return $left + $right;
    }
}
