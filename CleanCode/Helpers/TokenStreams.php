<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use WeakMap;

/**
 * The one answer to "which token stream is this, so a per-stream cache can tell
 * it apart from every other?".
 *
 * Several sniffs here build an index once per token stream — a line-start map,
 * an enclosure map, a class-like index, a record of walked chain roots — and
 * answer many `process()` calls from it. Each index holds pointers into one
 * particular stream, so it must be discarded the moment the stream it describes
 * is no longer the one being processed. Every one of them used to decide that
 * with its own copy of the same three-part string — the file name, the token
 * count, and the fixer's loop counter — and every copy shared the same hole:
 * two sources analysed as STDIN report the same name, so two of them that also
 * tokenise to the same count are indistinguishable. One `Ruleset` reused across
 * several `DummyFile`/STDIN analyses — what `tests/Helpers.php` does, and what
 * embedding PHP_CodeSniffer as a library does — hands every analysis the same
 * sniff instance, and the second one is then answered from the first one's
 * index. Issue #343 has the reproduction: a wrong indent in the diagnostic, and
 * violations silently missed.
 *
 * The key this class builds closes that hole by identifying the `File` object
 * itself rather than describing it:
 *
 * - **The stream identity** separates two analyses. It is a counter handed out
 *   once per `File` instance and never handed out again, held in a `WeakMap`
 *   keyed by the file object. `WeakMap` is what makes it sound where
 *   `spl_object_id()` is not: an object-handle id is recycled once its object
 *   is collected, so a later, unrelated `DummyFile` can inherit an earlier
 *   one's id and reproduce this very collision, while a `WeakMap` entry is
 *   dropped with the object it belongs to and the next file takes the next
 *   counter value.
 * - **The token count and the fixer's loop counter** separate the several
 *   re-tokenisations of *one* file that `phpcbf` performs, which the identity
 *   cannot: `Fixer::fixFile()` re-tokenises the same `File` object up to fifty
 *   times, so the object is unchanged while the pointers into it are not.
 *
 * The file name is deliberately absent. It is a property of the `File` object,
 * so the identity already separates every pair of files the name could have,
 * and two names for one stream cannot arise.
 *
 * The identity is established **once per `File` instance**, not once per
 * `process()` call. Absorbing many `process()` calls per stream without
 * rebuilding is the entire reason these indexes exist, so a discriminator that
 * changed per call would put back the quadratic cost each of them was written
 * to remove.
 */
final class TokenStreams
{
    /**
     * File instance => the identity handed out for it.
     *
     * Weak, so a `DummyFile` the caller no longer holds is collected as usual
     * and this map does not grow across a long-lived process. Built lazily
     * because a static property cannot be initialised with an object.
     */
    private static ?WeakMap $identities = null;

    /**
     * The last identity handed out, so the next one is always higher.
     */
    private static int $lastIdentity = 0;

    /**
     * The cache key for the token stream $phpcsFile currently holds.
     *
     * Two calls return the same key exactly while the same `File` object holds
     * the same token stream: a different file, or the same file re-tokenised by
     * another `phpcbf` pass, gives a different one.
     */
    public static function key(File $phpcsFile): string
    {
        return self::identity($phpcsFile)
            . '|' . count($phpcsFile->getTokens())
            . '|' . ($phpcsFile->fixer->loops ?? 0);
    }

    /**
     * The identity of the given file object, allocating one on first sight.
     */
    private static function identity(File $phpcsFile): int
    {
        self::$identities ??= new WeakMap();

        return self::$identities[$phpcsFile] ??= ++self::$lastIdentity;
    }
}
