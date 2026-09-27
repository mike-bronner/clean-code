<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\DummyFile;

const TOKEN_STREAMS_SOURCE = "<?php\n\n\$one = 1;\n";

it('gives one file the same key every time', function (): void {
    [$config, $ruleset] = buildRuleset();
    $file = new DummyFile(TOKEN_STREAMS_SOURCE, $ruleset, $config);
    $file->parse();
    $tokenStreams = new TokenStreams();

    expect($tokenStreams->key($file))->toBe($tokenStreams->key($file));
});

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
