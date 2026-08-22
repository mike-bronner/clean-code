<?php

// A business-domain namespace that happens to spell a suite name, on a file
// that really does sit under a suite directory. The namespace carries no test
// root, which places the class outside the test tree whatever its location
// says — so the suite rule does not speak about it.
//
// This is the case the root anchor exists for. Let the path speak anyway and
// this reports NamespaceMismatch, telling a domain class to declare a `Unit`
// namespace segment it has no business carrying.

namespace App\Domain\Feature;

class ToggleTest
{
}
