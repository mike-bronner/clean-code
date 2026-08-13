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

// Negative: the global namespace, spelled as an unnamed block. The location
// alone is what identifies this as a controller, and the global namespace
// contributes no segments to compare — no API one included.
namespace {
    class LegacyController
    {
    }
}
