<?php

// A trait use and an import share the T_USE token, and only the import binds a
// name. Telling them apart by reading the statement is what fails: an import
// ends at a semicolon, and a trait use whose adaptation block is empty carries
// none of its own. A scan for one runs past the class, past the block, and
// stops at the next statement that does have a semicolon — the import below.
// Its entries then bind against *this* block, which imported nothing, and the
// call in it goes quiet.
//
// Nothing between the trait use and that import may carry a semicolon, or the
// scan stops early and the shape is never exercised. The class body is last in
// its block for that reason, and the call sits above it.

namespace App\Feature {
    trait Loggable
    {
    }

    trait Cacheable
    {
    }

    // Positive: this block holds no import at all, so the call reaches PHP's
    // own function however far a scan for the trait use's end would travel.
    probeAdaptationLeak($value);

    class Widget
    {
        use Loggable, Cacheable {}
    }
}

namespace App\Other {
    // The mixed group is what the leak binds from: the statement does not lead
    // with `function`, so only the individual entry carries it, and that entry
    // is read whatever the statement it was reached through.
    use Acme\{ClassA, function probeAdaptationLeak, const X};

    // Negative: here the import is real and does redirect the call.
    probeAdaptationLeak($value);
}
