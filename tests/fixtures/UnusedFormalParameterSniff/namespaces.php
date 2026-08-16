<?php

declare(strict_types=1);

/**
 * Same-file override resolution across more than one namespace block.
 *
 * Every declaration here is silent, and each one is silent for a reason the
 * short name alone cannot supply: the file declares two class-likes called
 * `Origin` and two called `Contract`, one of each per namespace, and only the
 * one in the namespace of the class that names it answers for the reference.
 *
 * Keyed by short name, the second `Origin` overwrote the first, so `Child`
 * resolved to a class that declares no `handle()` and its override exemption
 * broke — a parameter reported on correct code. That is the direction that
 * matters here: the parity fixtures cover what the sniff must report, and this
 * one covers what it must not.
 *
 * A braced `namespace` is required to put two of them in one file, so this
 * fixture cannot be folded into passing.php, whose declarations sit under a
 * single unbraced namespace.
 *
 * tests/Standards/UnusedFormalParameterTest.php holds the assertion.
 */

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter\First {
    interface Contract
    {
        public function describe(string $label): string;
    }

    class Origin
    {
        public function handle(string $payload): string
        {
            return $payload;
        }
    }

    class Child extends Origin implements Contract
    {
        // Overrides First\Origin::handle(). The same-name class in the second
        // namespace declares no handle() at all.
        public function handle(string $payload): string
        {
            return 'handled';
        }

        // Implements First\Contract::describe(). The second namespace's
        // Contract declares no describe().
        public function describe(string $label): string
        {
            return 'described';
        }
    }
}

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedFormalParameter\Second {
    interface Contract
    {
        public function unrelated(string $label): string;
    }

    class Origin
    {
        public function unrelated(string $payload): string
        {
            return $payload;
        }
    }

    // Overrides Second\Origin::unrelated(), which is the Origin declared in
    // this namespace and not the one declared above it.
    class Child extends Origin implements Contract
    {
        public function unrelated(string $payload): string
        {
            return 'unrelated';
        }
    }
}
