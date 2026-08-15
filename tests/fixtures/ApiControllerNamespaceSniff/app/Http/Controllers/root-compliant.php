<?php

// The third path shape, and the one the other fixtures here cannot reach: this
// file sits *directly* on app/Http/Controllers/, with no subdirectory under it.
// Its path therefore carries a Controllers segment with nothing below it — the
// empty answer the sniff keeps deliberately distinct from "no controller root
// at all", and the ordinary Laravel layout for a view controller.

// Positive: neither side is API, so the two agree and the sniff stays silent.

namespace App\Http\Controllers;

class HomeController
{
}
