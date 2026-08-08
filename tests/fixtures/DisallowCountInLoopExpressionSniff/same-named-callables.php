<?php

// Every loop below puts the name `count` or `sizeof` inside a real loop
// condition, where the sniff is looking, but none of them is a call to the
// global function. This fixture isolates the callable check: drop any one
// member of NON_FUNCTION_CALL_PRECEDERS and a loop here is reported.
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

for ($i = 0; $i < namespace\sizeof($items); $i++) {
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
