<?php

// The NamespaceMismatch direction: the location names the `Unit` suite and the
// namespace contradicts it. Two ways to contradict it, one class each.

// Names a different suite.
namespace Tests\Feature {
    class ReportTest
    {
    }
}

// Names no suite at all, while still sitting inside the test tree.
namespace Tests\Support {
    class LedgerTest
    {
    }
}
