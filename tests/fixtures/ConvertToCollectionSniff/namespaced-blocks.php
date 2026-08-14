<?php

// A named block and a global block in one file, the shape that separates "this
// file declares a namespace" from "a namespace is in force here". Only the
// nearest declaration counts, so the two namespace\ calls below get opposite
// verdicts even though both sit in a file that does declare a namespace.

namespace App\Support {
    // Silent: the enclosing declaration is named, so namespace\array_filter()
    // is App\Support\array_filter().
    $active = namespace\array_filter($rows);
}

namespace {
    // Flagged: a bare namespace block *is* the global namespace, so
    // namespace\array_map() is the native function.
    $names = namespace\array_map('trim', $rows);

    // Flagged too, and here to prove the walk back does not stop at the
    // namespace\ keyword above: reading that operator as a declaration would
    // decide this call's namespace from a token that declares nothing.
    $active = namespace\array_filter($rows);
}
