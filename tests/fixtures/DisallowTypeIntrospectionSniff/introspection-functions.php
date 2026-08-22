<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

final class Inspector
{
    public function byClassName(object $value): string
    {
        if (get_class($value) === 'DateTimeImmutable') {
            return 'date';
        }

        return 'other';
    }

    public function byDebugType(mixed $value): string
    {
        return get_debug_type($value) === 'int' ? 'number' : 'other';
    }

    public function byGettype(mixed $value): string
    {
        switch (gettype($value)) {
            case 'integer':
                return 'number';
            default:
                return 'other';
        }
    }

    public function byRelationship(object $value): string
    {
        return match (true) {
            is_a($value, \Throwable::class) => 'error',
            is_subclass_of($value, \Exception::class) => 'exception',
            default => 'other',
        };
    }

    public function byRootNamespacedCall(object $value): string
    {
        if (\get_class($value) === 'stdClass') {
            return 'plain';
        }

        return 'other';
    }

    public function byUppercasedCall(object $value): string
    {
        if (GET_CLASS($value) === 'stdClass') {
            return 'plain';
        }

        return 'other';
    }

    public function whileSubclass(object $value): int
    {
        $depth = 0;

        while (is_subclass_of($value, \Exception::class)) {
            $depth++;

            break;
        }

        return $depth;
    }

    public function inACaseLabel(mixed $value): string
    {
        switch (true) {
            case gettype($value) === 'integer':
                return 'number';
            default:
                return 'other';
        }
    }

    /**
     * A `match` subject, which decides which arm runs the same way a `switch`
     * subject decides which case does.
     */
    public function asAMatchSubject(mixed $value): string
    {
        return match (get_debug_type($value)) {
            'int' => 'number',
            default => 'other',
        };
    }

    /**
     * A variadic unpack is an ordinary call, not the first-class callable
     * syntax that shares its `...` — there is an argument after the ellipsis,
     * and the call runs here.
     *
     * @param array<int, mixed> $args
     */
    public function byVariadicUnpack(array $args): string
    {
        if (is_a(...$args)) {
            return 'related';
        }

        return 'other';
    }
}
