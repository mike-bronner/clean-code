<?php

// The counterpart to failing.php's `namespace\count(…)` loop. Both spell the
// same thing; only the namespace in force differs, and that is what decides
// whether the name reaches PHP's own function.
//
// Here a named namespace is declared, so `namespace\` resolves to
// `App\Support\` — somebody else's function that merely shares the short name,
// and nothing this rule has anything to say about. In failing.php no namespace
// is declared, the relative qualifier reaches the global one, and the identical
// line is reported.
//
// This file is what keeps the fix honest: a sniff that simply reported every
// `namespace\count(…)` would pass failing.php's assertion and redden here.

namespace App\Support;

// A bare call in a named namespace still falls back to PHP's own function, so
// this one is reported — it is here to prove the file is reached at all, and
// that the silence below is about the qualifier rather than about the
// namespace declaration switching the sniff off.
while (count($rows) > 0) {
    array_pop($rows);
}

// Silent: `namespace\count()` is `App\Support\count()` here.
while (namespace\count($rows) > 0) {
    array_pop($rows);
}

for ($i = 0; $i < namespace\sizeof($items); $i++) {
    echo $items[$i];
}
