<?php

// `namespace\` resolves against the namespace block the call sits in, not
// against the file. Both branches below are spelled identically and only the
// enclosing block differs, so a sniff reading every `namespace\`-qualified name
// as never-global — which is what the hand-rolled qualifier walker did — cannot
// satisfy both halves.
//
// Only a braced block can be unnamed, and only a braced file can hold a second
// block to contrast it with, so this case has no unbraced spelling and cannot
// be folded into a fixture that declares one namespace the ordinary way.

namespace {
    final class UnnamedBlock
    {
        /**
         * The unnamed block *is* the global namespace, so `namespace\get_class()`
         * is PHP's own get_class() written the long way round. Reported.
         */
        public function byRelativeName(object $value): string
        {
            if (namespace\get_class($value) === 'stdClass') {
                return 'plain';
            }

            return 'other';
        }
    }
}

namespace CleanCodeFixtures\TypeIntrospection\Named {
    final class NamedBlock
    {
        /**
         * The identical line under a named block reaches
         * CleanCodeFixtures\TypeIntrospection\Named\get_class() — somebody
         * else's function that merely shares the short name. Silent.
         */
        public function byRelativeName(object $value): string
        {
            if (namespace\get_class($value) === 'stdClass') {
                return 'plain';
            }

            return 'other';
        }

        /**
         * The bare name still falls back to PHP's own function here, which
         * proves this block is reached at all and that the silence above is
         * about the qualifier rather than about the block switching the sniff
         * off.
         */
        public function byBareName(object $value): string
        {
            if (get_class($value) === 'stdClass') {
                return 'plain';
            }

            return 'other';
        }
    }
}
