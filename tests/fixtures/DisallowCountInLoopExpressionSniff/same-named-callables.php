<?php

// Everything below puts the name `count` or `sizeof` inside a real loop
// condition, where the sniff is looking, but none of them is a call to the
// global function. This fixture isolates the callable check: drop it and every
// loop here is reported.

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

class Inventory
{
    // Declaring a method of that name is not calling it. The declaration sits
    // inside a loop condition's reach only in the sense that the sniff sees the
    // T_STRING; the preceding T_FUNCTION is what rules it out.
    public function count(): int
    {
        return 0;
    }

    public function sizeof(): int
    {
        return 0;
    }
}
