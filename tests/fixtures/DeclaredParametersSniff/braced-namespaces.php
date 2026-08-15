<?php

declare(strict_types=1);

// Braced namespace blocks: the block a call sits *in* decides what
// `namespace\` resolves against, not whichever declaration happens to appear
// above it in the file. A named block on either side of the global one must
// not exempt a call that is in the global namespace.

namespace Acme\Before {
    class Before
    {
        public function relative(): array
        {
            // Resolves against Acme\Before — not PHP's function.
            return namespace\func_get_args();
        }
    }
}

namespace {
    class GlobalScope
    {
        public function relative(): array
        {
            // The current namespace here *is* the global one, however many
            // named blocks precede it, so this is PHP's own function.
            return namespace\func_get_args();
        }

        public function unqualified(): int
        {
            return func_num_args();
        }
    }
}

namespace Acme\After {
    class After
    {
        public function relative(): int
        {
            // Resolves against Acme\After — not PHP's function.
            return namespace\func_num_args();
        }

        // A magic method keeps its exemption inside a braced block.
        public function __call(string $name, array $arguments): array
        {
            return func_get_args();
        }
    }
}
