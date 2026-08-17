<?php

// This fixture's own path puts `fixtures` directly below its nearest `tests`
// root, so the location names no suite. Every class below declares one anyway,
// which is the DirectoryMismatch direction: the namespace says which suite the
// test belongs to and the directory does not agree.

// The three shipped suite names, one class each, so no single spelling can
// stand in for the others.
namespace Tests\Unit {
    class ReportTest
    {
    }
}

namespace Tests\Feature {
    class BillingTest
    {
    }
}

namespace Tests\Integration {
    class GatewayTest
    {
    }
}

// Both the test root and the suite segment compared case-insensitively. These
// are two separate normalizations: match the root literally and this namespace
// has no test root at all, so the class is vetoed and never reported.
namespace TESTS\UNIT {
    class LowercaseTest
    {
    }
}

// The base-class route into the rule. This class does not carry the `Test`
// suffix, so it is only recognized as a test by what it extends.
namespace Tests\Unit {
    use PHPUnit\Framework\TestCase;

    class Reports extends TestCase
    {
    }
}

// The same route with the parent written out as a fully qualified name. Only
// the trailing segment is compared, so this matches the configured `TestCase`
// exactly as the imported short name above does.
namespace Tests\Feature {
    class Invoices extends \PHPUnit\Framework\TestCase
    {
    }
}

// T_NAMESPACE is also the `namespace\` relative-name operator. The nearest one
// above the second class here is that operator, in the first class's body — read
// as a declaration it resolves the class to a namespace of `formatted()`, which
// carries no test root, and the misplaced test is never reported.
namespace Tests\Integration {
    class ShipmentTest
    {
        public function format(): string
        {
            return namespace\formatted();
        }
    }

    class ManifestTest
    {
    }
}
