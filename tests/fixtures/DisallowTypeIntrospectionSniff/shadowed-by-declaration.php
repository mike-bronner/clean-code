<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

/**
 * A function declared in the file's own namespace takes precedence over the
 * global function of the same name, so an unqualified call reaches this one.
 * This is the ordinary shape of a helper-function library.
 */
function get_class(object $value): string
{
    return 'thing';
}

final class Declarer
{
    /**
     * Resolves to the function above, not to the global `get_class()`.
     */
    public function byDeclaredFunction(object $value): string
    {
        if (get_class($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    /**
     * Control: an explicit root qualifier outranks the file's own declaration,
     * so this is the global function and is reported.
     */
    public function byRootQualifiedName(object $value): string
    {
        if (\get_class($value) === 'stdClass') {
            return 'plain';
        }

        return 'other';
    }

    /**
     * Control: a *method* of the same name shadows nothing — an unqualified
     * call never reaches a class member — so the global function is reported
     * even though `is_a()` is declared just below.
     */
    public function byNameAlsoDeclaredAsAMethod(object $value): string
    {
        if (is_a($value, \Throwable::class)) {
            return 'error';
        }

        return 'other';
    }

    private function is_a(object $value, string $class): bool
    {
        return false;
    }

    /**
     * Control: an introspection function this file does not declare is still
     * the global one.
     */
    public function byUndeclaredName(mixed $value): string
    {
        if (gettype($value) === 'integer') {
            return 'number';
        }

        return 'other';
    }
}
