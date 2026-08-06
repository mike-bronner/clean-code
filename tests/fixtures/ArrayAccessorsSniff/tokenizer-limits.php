<?php

declare(strict_types=1);

// Pins a PHP_CodeSniffer tokenizer defect the sniff cannot see past, so that an
// upstream fix shows up here as a failing test rather than going unnoticed.
//
// A `foreach` whose target is a dynamic member holding a brace-bearing
// expression -- `$order->{match (...) { ... }}`, `$order->{(function () { ... })()}`
// -- leaves PHP_CodeSniffer unable to record the loop's scope. Without a
// scope_opener on the closing brace, the tokenizer's short-array check falls
// through to "a `}` precedes this `[`, so it indexes something", and the *next*
// statement's destructuring pattern is tokenized as T_OPEN_SQUARE_BRACKET
// instead of T_OPEN_SHORT_ARRAY.
//
// The sniff reads that label to tell an index (a read: `$target[$key['idx']]`)
// from a destructuring pattern (a write: `[$target['a']] = $source`). Told the
// pattern is an index, it reports the write target $target on line 30 as though
// it were an offset read. The mislabelling happens before any sniff runs and
// destroys the only signal that separates the two, so this is recorded rather
// than worked around: reconstructing the distinction would mean re-deriving it
// from token data already known to be wrong.
class ArrayAccessorsTokenizerLimits
{
    public function corruptsTheFollowingStatement(array $rows, array $payload, array $target, array $source, object $order): array
    {
        foreach ($rows as $order->{match (true) { default => $payload['member'] }}) {
        }

        [$target[$payload['first']]] = $source;

        return [$target, $order];
    }

    // The same pair with an ordinary dynamic member in the loop target. The
    // scope is recorded, the pattern is tokenized as a short array, and only
    // the two reads are reported -- which is what the line above should do.
    public function readsCorrectlyWithoutTheDefect(array $rows, array $payload, array $target, array $source, object $order): array
    {
        foreach ($rows as $order->{$payload['member']}) {
        }

        [$target[$payload['first']]] = $source;

        return [$target, $order];
    }
}
