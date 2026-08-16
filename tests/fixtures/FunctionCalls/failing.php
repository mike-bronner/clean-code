<?php

// Every `probe*` call in this fixture reaches PHP's own global function, so the
// helper must say so. The imports at the top are the near misses: none of them
// takes part in resolving a function call, and none may suppress one.

namespace App;

use Acme\Support\probeClassImport;
use const Acme\Support\probeConstantImport;
use function Acme\Support\probeAliasedAway as probeAliasTarget;
use function Other\Space\probeOtherBlock;
use Acme\{function\probeSegmentNamed, Collector};

// Positive: nothing redirects a bare name, so it falls back to the global one.
probeBare($value);

// Positive: PHP 8 allows a reserved word as a name segment, so the `function`
// above names part of the namespace imported *from* rather than prefixing the
// entry. That entry is a class import, and binds no function name.
probeSegmentNamed($value);

// Positive: a leading separator qualifies the global namespace explicitly.
\probeFullyQualified($value);

// Positive: an `&` directly before the name is the return-by-reference marker
// only where a declaration puts it there, between `function` and the name.
// Here it is the bitwise operator, and what follows it is a call like any
// other. Without this case, "any preceding `&` means a declaration" reads the
// same as the real rule, and the passing fixture's `function &probe…()` cases
// cannot tell the two apart on their own.
$flags = $mask & probeBitwiseOperator($value);

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

// Positive: the same shape with a *qualified* name in the nested closure, which
// is what makes the capture list's leading `(` load-bearing. Read as an import
// list, this statement's second entry leads with `function` and names a symbol
// outside the global namespace, so nothing downstream discards it — the entry
// binds `probeCaptureLeak` and silences the bare call below. Only the leading
// keyword the statement lacks keeps its span from ever being read that way.
$leaking = function () use ($value) {
    return [1, function () { return \Acme\Support\probeCaptureLeak($value); }];
};

probeCaptureLeak($value);

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
