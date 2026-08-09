<?php

// Every `probe*` call in this fixture reaches PHP's own global function, so the
// helper must say so. The imports at the top are the near misses: none of them
// takes part in resolving a function call, and none may suppress one.

namespace App;

use Acme\Support\probeClassImport;
use const Acme\Support\probeConstantImport;
use function Acme\Support\probeAliasedAway as probeAliasTarget;
use function Other\Space\probeOtherBlock;

// Positive: nothing redirects a bare name, so it falls back to the global one.
probeBare($value);

// Positive: a leading separator qualifies the global namespace explicitly.
\probeFullyQualified($value);

// Positive: a class import binds a class name; function resolution ignores it.
probeClassImport($value);

// Positive: a constant import binds a constant; likewise ignored.
probeConstantImport($value);

// Positive: the import bound the alias, so the *source* name is still global.
probeAliasedAway($value);

// Positive: a closure's `use (...)` captures variables, it imports nothing.
$callback = function () use ($value) {
    return probeInsideClosure($value);
};

// Positive: and it still imports nothing when a comma inside the statement is
// followed by the `function` keyword — that is a nested closure, not an import
// entry. Reading a capture list as a list of import entries would take this
// name for an imported one and go quiet on both calls to it.
$handlers = function () use ($value) {
    return [1, function () { return probeInsideCapture($value); }];
};

probeInsideCapture($value);

// Positive: a trait `use` inside a class body imports no function either.
class Consumer
{
    use ProbeTrait;

    public function run(mixed $value): mixed
    {
        return probeInsideMethod($value);
    }
}

// Positive: the import above lives in this file's other namespace block, so it
// does not reach this one.
namespace Other;

probeOtherBlock($value);
