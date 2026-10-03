<?php

// A relative call inside a qualified namespace declaration resolves into that
// namespace, so it is not a call to the global function.

namespace App\Sub;

namespace\probeRelativeInQualified($value);
