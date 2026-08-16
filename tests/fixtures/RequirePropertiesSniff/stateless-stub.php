<?php

declare(strict_types=1);

namespace App\Fixtures;

/**
 * One PSR-12-perfect file holding one stateless class and nothing else, so the
 * existing-sniff search in tests/Standards/RequirePropertiesTest.php can ask a
 * whole vendor standard a single question: does anything already report a class
 * for encapsulating no state? Any violation at all from a standard other than
 * CleanCode is an answer, which is only readable while the file gives the
 * standards nothing else to talk about.
 */
class Marker
{
}
