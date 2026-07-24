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
}
