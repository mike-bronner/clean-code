<?php

// A suite name deeper in the path than the segment directly below the test
// root. `Support` is what this test is filed under; the `Unit` below it is that
// suite's own structure, not a second filing. The namespace says `Support` too,
// so the two agree and nothing is reported.
//
// Search the whole path below the root for a suite name instead of reading the
// segment directly below it, and this resolves to the `Unit` suite while the
// namespace still says none — NamespaceMismatch on a correctly filed helper.

namespace Tests\Support;

class LedgerTest
{
}
