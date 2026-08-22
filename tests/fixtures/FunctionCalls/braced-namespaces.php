<?php

// The braced `namespace A { … }` form, which the unbraced fixtures cannot
// reach: a braced declaration carries a scope closer, so the block it owns
// ends at that brace instead of running on to the next declaration. Every
// block below calls the same name, and only the one holding the import may
// treat that call as redirected.
//
// PHP forbids mixing the two forms in one file, so this shape needs a fixture
// of its own rather than a few more lines in passing.php or failing.php.

namespace App {
    use function Acme\Support\probeBracedImport;

    // Negative: inside the importing block, the import redirects the call.
    probeBracedImport($value);
}

namespace Other {
    // Positive: a sibling block is past the importing block's closing brace,
    // so the import has no say here and the call reaches PHP's own function.
    probeBracedImport($value);
}

namespace {
    // Positive: the global block is a block like any other, and the import
    // above does not reach into it either.
    probeBracedImport($value);
}
