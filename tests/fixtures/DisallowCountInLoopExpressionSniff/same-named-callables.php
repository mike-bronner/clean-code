<?php

// Every loop below puts the name `count` or `sizeof` inside a real loop
// condition, where the sniff is looking, but none of them is a call to the
// global function. This fixture isolates the callable check: every shape here
// is silent only because FunctionCalls::isGlobalFunctionCall() says so, so
// dropping any one of its NON_CALL_PRECEDERS members — or its import
// resolution — reports a loop here.
//
// The `class count` declaration near the end is the one construct here that is
// *not* in a loop condition. It is supporting scaffolding, so the `new count(…)`
// below it names a class that exists; the sniff never scans it, and it pins
// nothing on its own.

// Reached through an object operator: a method on some other class.
while ($collection->count() > 0) {
    $collection->pop();
}

while ($collection?->sizeof() > 0) {
    $collection->pop();
}

// Behind a double colon: a static method.
while (Collection::count($items) > 0) {
    array_pop($items);
}

for ($i = 0; $i < Collection::sizeof($items); $i++) {
    echo $items[$i];
}

// A qualified name resolves outside the global namespace, so it is a different
// function that merely shares the short name.
while (App\Support\count($items) > 0) {
    array_pop($items);
}

// `use function` redirects the bare name to somebody else's function, so the
// call below never reaches PHP's own count(). This file has no namespace of its
// own, so the import binds in the global one, which is where the call sits.
// Only FunctionCalls::isGlobalFunctionCall() resolves imports — the hand-rolled
// preceder list this sniff used to carry read the bare name as the global
// function and reported this loop.
use function App\Support\sizeof;

for ($i = 0; $i < sizeof($items); $i++) {
    echo $items[$i];
}

// The bare name with no argument list is a constant or a property, not a call.
while ($mode === count) {
    break;
}

while ($object->count > 0) {
    $object->consume();
}

// Declaring a method of that name is not calling it. An anonymous class
// expression is how a declaration reaches a loop *condition*, which is the only
// place the sniff looks — a named class declared beside the loops would never
// be scanned at all. The preceding T_FUNCTION is what rules the declarations
// out; the `->count()` that drives the loop is ruled out by the object operator
// above it.
while ((new class {
    public function count(): int
    {
        return 0;
    }

    public function sizeof(): int
    {
        return 0;
    }
})->count() > 0) {
    break;
}

// Instantiating a class that shares the short name is not calling the function.
// PHP class names and function names live in separate symbol tables, so `count`
// is a legal class name; the preceding T_NEW is what rules it out.
class count
{
    public function hasMore(): bool
    {
        return false;
    }
}

while ((new count($items))->hasMore()) {
    break;
}
