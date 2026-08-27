<?php

declare(strict_types=1);

namespace CleanCodeFixtures\TypeIntrospection;

use function CleanCodeFixtures\Helpers\get_class;
use function CleanCodeFixtures\Helpers\describe as gettype;
use function CleanCodeFixtures\Helpers\{is_a, relate as is_subclass_of};

/**
 * A `use function` import rebinds an unqualified name for the whole file, so a
 * bare call reaches the imported function and not the global one of the same
 * name. Only the names this file leaves alone are still introspection.
 */
final class Importer
{
    /**
     * Imported under its own name.
     */
    public function byImportedName(object $value): string
    {
        if (get_class($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    /**
     * Imported under an alias that collides with an introspection function.
     */
    public function byAlias(mixed $value): string
    {
        if (gettype($value) === 'thing') {
            return 'thing';
        }

        return 'other';
    }

    /**
     * A braced group import binds every name in the list, aliases included.
     */
    public function byGroupImport(object $value): string
    {
        if (is_a($value, 'thing')) {
            return 'thing';
        }

        if (is_subclass_of($value, 'thing')) {
            return 'subthing';
        }

        return 'other';
    }

    /**
     * Control: an introspection function this file does not import is still the
     * global one, so branching on it is still reported.
     */
    public function byUnimportedName(mixed $value): string
    {
        if (get_debug_type($value) === 'int') {
            return 'number';
        }

        return 'other';
    }

    /**
     * Control: an explicit root qualifier outranks the import — `\get_class()`
     * is the global function however the bare name resolves, and is reported.
     */
    public function byRootQualifiedName(object $value): string
    {
        if (\get_class($value) === 'stdClass') {
            return 'plain';
        }

        return 'other';
    }
}
