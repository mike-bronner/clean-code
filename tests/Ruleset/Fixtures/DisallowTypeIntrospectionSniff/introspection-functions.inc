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
}
