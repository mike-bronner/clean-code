<?php

// This fixture's own path is tests/fixtures/TestSuiteNamespaceSniff/, so the
// segment directly below its nearest `tests` root is `fixtures` — which names
// no suite. Every namespace below is therefore judged against a location that
// claims no suite of its own, and each one is silent for its own reason.
// Braced blocks let one file carry several namespaces; the sniff resolves each
// class against the block enclosing it.

// Positive: both sides agree that this is not a suite. The sniff registers on
// the class, reads a test root on the namespace side, finds no suite there and
// none on the path either. It is not silent for want of anything to look at.
namespace Tests\Support {
    class HelperTest
    {
    }
}

// Positive: `Feature` here is a business-domain segment, not a suite — the
// namespace carries no test root above it, so the class sits outside the test
// tree whatever its location says. Without the root anchor this reads as a
// feature test filed under no suite directory.
namespace App\Domain\Feature {
    class ToggleTest
    {
    }
}

// Positive: segments are compared whole. `UnitOfWork` below the test root is
// not the `Unit` suite; a prefix match would report this class.
namespace Tests\UnitOfWork {
    class LedgerTest
    {
    }
}

// Positive: a class sitting directly on the test root. Nothing follows the
// root, so the namespace names no suite — the same answer the path gives.
namespace Tests {
    class BootstrapTest
    {
    }
}

// Positive: not a test class. It carries neither the configured `Test` suffix
// nor a configured base class, so the suite rule does not speak about it even
// though it is declared in a suite namespace.
namespace Tests\Unit {
    class SupportHelper
    {
    }
}
