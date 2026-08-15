<?php

declare(strict_types=1);

// The staggered shape of unterminated.php:19 -- an opener that never closes,
// sitting partway up a staircase rather than beside the read. The walk outward
// cannot step over it, so it stops there and the read is reported, rather than
// trusting a closer it cannot prove belongs to the same construct.
//
// Which reads that covers is the point. The unterminated `foo(` sits directly
// inside the `sprintf` parentheses, so it stands between them and everything
// after it: the read beside it, the reads deeper in the same argument list,
// and the read outside it in the pattern all keep reporting. Continuing the
// walk instead would find the pattern's `]` and take every one of them for a
// destructuring target, dropping reads on a statement PHP rejects anyway.
//
// Deliberately last in the file: the opener has to stay unterminated, so
// nothing can follow that would close it.
[$outer['first'], sprintf('%s%s', $middle['second'], strtoupper($inner['third']) . foo(] = $source;
