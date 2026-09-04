<?php

/**
 * Tests MikeBronner\CleanCode\Helpers\TokenStreams directly.
 *
 * The helper is the one answer to "which token stream is this?", shared by the
 * four sniffs that build a per-stream index. Testing it through one of them
 * would only see the collisions that sniff's own fixtures happen to produce, so
 * these tests read the key it builds for streams constructed on purpose.
 *
 * There are no fixtures on disk here, and there deliberately cannot be: the
 * whole point of the helper is telling apart two analyses that share a file
 * name, which only STDIN sources do.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\DummyFile;

const TOKEN_STREAMS_SOURCE = "<?php\n\n\$one = 1;\n";

/**
 * The key exists to be compared against itself on the next process() call, so
 * a key that changed between two reads of one unchanged file would rebuild
 * every index on every call — the quadratic cost each index was written to
 * remove.
 */
it('gives one file the same key every time', function (): void {
    [$config, $ruleset] = buildRuleset();
    $file = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $file->parse();
    $tokenStreams = new TokenStreams();

    expect($tokenStreams->key($file))->toBe($tokenStreams->key($file));
});

/**
 * The collision issue #343 records: two sources analysed as STDIN report the
 * same file name, and two that also tokenise to the same count were
 * indistinguishable under the old file/count/loop key. Identical content is the
 * strongest form of that — same name, same count, same loop counter — so it is
 * what the key has to separate.
 */
it('gives two files different keys, identical content included', function (): void {
    [$config, $ruleset] = buildRuleset();
    $first = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $second = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $first->parse();
    $second->parse();
    $tokenStreams = new TokenStreams();

    expect($first->getFilename())->toBe($second->getFilename())
        ->and(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and($tokenStreams->key($first))->not->toBe($tokenStreams->key($second));
});

/**
 * Why the identity is held in a WeakMap rather than read from
 * spl_object_id()/spl_object_hash(): PHP recycles an object handle once its
 * object is collected, so a DummyFile the caller no longer holds — the very
 * shape issue #343 is about, since analyzeStdinSource() returns one and drops
 * the last one — hands its id straight to the next file and reproduces the
 * collision.
 *
 * This test pins the recycling rather than assuming it: it asserts the second
 * file really did inherit the first one's object id, so the case it guards
 * against is the one PHP actually produces here, and then asserts the keys
 * differ anyway.
 */
it('does not hand a collected file identity to the next one', function (): void {
    [$config, $ruleset] = buildRuleset();

    $tokenStreams = new TokenStreams();
    $first = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $first->parse();
    $firstKey = $tokenStreams->key($first);
    $firstObjectId = spl_object_id($first);

    unset($first);

    $second = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $second->parse();

    expect(spl_object_id($second))->toBe($firstObjectId)
        ->and($tokenStreams->key($second))->not->toBe($firstKey);
});
