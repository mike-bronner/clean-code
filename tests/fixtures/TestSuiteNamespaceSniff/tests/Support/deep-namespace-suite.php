<?php

// The same rule on the namespace side: `Unit` here sits below `Support`, which
// is the segment the test is actually filed under, and the path agrees.
//
// Search the whole namespace below the root and this resolves to the `Unit`
// suite while the path still says none — DirectoryMismatch on a correctly filed
// helper.

namespace Tests\Support\Unit;

class CalculatorTest
{
}
