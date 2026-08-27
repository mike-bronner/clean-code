<?php

/**
 * An import binds inside its own namespace block and nowhere else.
 *
 * This sniff used to collect `use function` imports itself, file-wide, so an
 * import anywhere credited every block with the shadow and the call in Second
 * below went unreported. Since #320 the question is
 * CleanCode\Helpers\FunctionCalls::isGlobalFunctionCall()'s, which resolves an
 * import against the block the call sits in.
 *
 * Both blocks spell the call identically, so the pair is what pins the
 * scoping: crediting the import file-wide silences the second, and ignoring
 * imports altogether reports the first.
 *
 * tests/Standards/DisallowTypeIntrospectionTest.php holds the assertion.
 */

namespace First {
    use function CleanCodeFixtures\Helpers\get_debug_type;

    class Importer
    {
        // Silent: the import redirects this bare name to Vendor\Package's own
        // function, which is not the introspection primitive this rule bans.
        public function decide(mixed $value): string
        {
            if (get_debug_type($value) === 'int') {
                return 'number';
            }

            return 'other';
        }
    }
}

namespace Second {
    class Bystander
    {
        // Reported: no import is in force in this block, so the bare name
        // falls back to PHP's own get_debug_type().
        public function decide(mixed $value): string
        {
            if (get_debug_type($value) === 'int') {
                return 'number';
            }

            return 'other';
        }
    }
}
