<?php

// `namespace\` resolves against the namespace block the call sits in, not
// against the file. Both loops below are spelled identically and only the block
// enclosing them differs, so a sniff answering this question by looking at the
// file — or by reading every `namespace\`-qualified name as never-global, which
// is what the hand-rolled qualifier walker did — cannot satisfy both halves.
//
// The unnamed block is the case namespaced-relative.php cannot reach: that file
// declares one named namespace with the unbraced syntax, where an unnamed
// namespace has no unbraced spelling at all. Only a braced block can be
// unnamed, and only a braced file can hold a second block to contrast it with.

namespace {
    // The unnamed block *is* the global namespace, so `namespace\count()` is
    // PHP's own count() written the long way round. Reported.
    while (namespace\count($rows) > 0) {
        array_pop($rows);
    }
}

namespace App\Support {
    // The identical line under a named block reaches App\Support\count() —
    // somebody else's function that merely shares the short name. Silent.
    while (namespace\count($rows) > 0) {
        array_pop($rows);
    }

    // The bare name still falls back to PHP's own function here, which proves
    // this block is reached at all and that the silence above is about the
    // qualifier rather than about the block switching the sniff off.
    while (count($rows) > 0) {
        array_pop($rows);
    }
}
