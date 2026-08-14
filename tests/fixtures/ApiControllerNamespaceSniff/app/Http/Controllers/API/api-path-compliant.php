<?php

// The path side of the rule, which the flat fixtures cannot reach: this file
// really does sit under app/Http/Controllers/API/, so the sniff reads an API
// path segment from its own location.

// Positive: the compliant API controller — an API namespace segment at an API
// path segment, the two in agreement.

namespace App\Http\Controllers\API;

class ReportController
{
}
