<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

final class Formatter
{
    public function format(object $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return 'date';
        } elseif ($value instanceof \Throwable) {
            return 'error';
        }

        return 'unknown';
    }

    public function shortLabel(object $value): string
    {
        return $value instanceof \Throwable ? 'error' : 'value';
    }

    public function matched(object $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => 'date',
            $value instanceof \Throwable, $value instanceof \Stringable => 'error',
            default => 'unknown',
        };
    }

    public function switched(object $value): string
    {
        switch (true) {
            case $value instanceof \Throwable:
                return 'error';
            default:
                return 'unknown';
        }
    }

    public function drain(object $value): int
    {
        $count = 0;

        while ($value instanceof \Iterator) {
            $count++;

            break;
        }

        return $count;
    }

    public function nested(object $value): string
    {
        if (in_array($value instanceof \Throwable, [true], true)) {
            return 'error';
        }

        return 'value';
    }

    public function ternaryBehindACall(object $value): string
    {
        return $this->wrap($value instanceof \Throwable) ? 'error' : 'value';
    }

    private function wrap(bool $flag): bool
    {
        return $flag;
    }

    /**
     * A callback bounds the search, but it does not exempt one: a branch
     * *inside* the closure is still a branch the check decides.
     */
    public function branchInsideAClosure(): callable
    {
        return function (object $value): string {
            if ($value instanceof \Throwable) {
                return 'error';
            }

            return 'value';
        };
    }

    public function ternaryInsideAnArrowFunction(): callable
    {
        return fn (object $value): string => $value instanceof \Throwable ? 'error' : 'value';
    }

    public function matchArmInsideAClosure(): callable
    {
        return function (object $value): string {
            return match (true) {
                $value instanceof \Throwable => 'error',
                default => 'value',
            };
        };
    }

    public function switchCaseInsideAClosure(): callable
    {
        return function (object $value): string {
            switch (true) {
                case $value instanceof \Throwable:
                    return 'error';
            }

            return 'value';
        };
    }

    /**
     * A callback that has already closed bounds nothing. This branch sits
     * after every closure above and in none of them, so it is reported like
     * any other.
     */
    public function branchAfterAClosureHasClosed(object $value): string
    {
        if ($value instanceof \Throwable) {
            return 'error';
        }

        return 'value';
    }

    /**
     * A callback earlier in the *same* condition closes before the check, so
     * it does not contain it: the check still decides this `if`.
     */
    public function branchBesideAClosedCallbackInTheSameCondition(object $value): string
    {
        if ($this->wrap((bool) array_map(fn ($item) => $item, [])) && $value instanceof \Throwable) {
            return 'error';
        }

        return 'value';
    }

    /**
     * The same shape in a ternary condition.
     */
    public function ternaryBesideAClosedCallback(object $value): string
    {
        return $this->wrap((bool) array_map(fn ($item) => $item, [])) && $value instanceof \Throwable
            ? 'error'
            : 'value';
    }
}
