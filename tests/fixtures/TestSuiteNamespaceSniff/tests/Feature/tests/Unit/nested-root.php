<?php

// Two `tests` roots on one path. The root closest to the file governs, so the
// suite read here is `Unit` and the namespace agrees. Anchor on the first root
// instead and the segment directly below it is `Feature`, which contradicts
// this namespace and reports NamespaceMismatch.

namespace Tests\Unit;

class CalculatorTest
{
}
