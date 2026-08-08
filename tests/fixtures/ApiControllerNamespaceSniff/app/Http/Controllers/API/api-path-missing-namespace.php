<?php

// The other half of the path side: this file sits under
// app/Http/Controllers/API/, so its location says API and its namespace has to
// agree.

// Negative: a controller namespace with no API segment, at an API path.
namespace App\Http\Controllers {
    class ReportController
    {
    }
}

// Negative: no namespace at all. The location alone is what identifies this as
// a controller, and the global namespace carries no API segment either.
namespace {
    class LegacyController
    {
    }
}
