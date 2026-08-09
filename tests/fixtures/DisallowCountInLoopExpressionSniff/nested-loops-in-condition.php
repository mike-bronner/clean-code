<?php

// A condition may carry a whole nested scope — a closure, an anonymous class,
// an arrow function, a match arm — and that scope may carry loops of its own.
// Every call below is re-evaluated each time the outer condition is tested, so
// PHPMD flags every one of them, and so does this sniff. What each shape pins
// is that it is flagged *once*: a nested loop's header is judged by that loop's
// own pass, not a second time by the enclosing loop's scan.

// A foreach body inside a closure in the condition. foreach is not registered,
// so this call can only be reached by the outer loop's scan.
while (someFn(function () use ($rows) {
    foreach ($rows as $row) {
        echo count($row);
    }

    return true;
})()) {
    echo 'outer';
}

// A nested for's *body*. The outer scan walks the body; the nested for's own
// pass sees only its header, so the call is reported once.
while (someFn(function () use ($rows) {
    for ($i = 0; $i < 3; $i++) {
        echo count($rows);
    }

    return true;
})()) {
    echo 'outer';
}

// A nested for's *condition*, which both loops can see. The outer scan steps
// over the nested header, leaving the report to the nested for's own pass.
while (someFn(function () use ($rows) {
    for ($i = 0; $i < count($rows); $i++) {
        echo $i;
    }

    return true;
})()) {
    echo 'outer';
}

// The same for a nested while.
while (someFn(function () use ($rows) {
    while (count($rows) > 0) {
        array_pop($rows);
    }

    return true;
})()) {
    echo 'outer';
}

// And for a nested do-while, whose condition hangs off its trailing while.
while (someFn(function () use ($rows) {
    do {
        array_pop($rows);
    } while (count($rows) > 0);

    return true;
})()) {
    echo 'outer';
}

// A nested foreach header is not a loop condition and is not skipped: foreach
// is unregistered, so nothing else would report this call.
while (someFn(function () use ($rows) {
    foreach (array_slice($rows, 0, count($rows)) as $row) {
        echo $row;
    }

    return true;
})()) {
    echo 'outer';
}

// An anonymous class's method body, reached through the object it constructs.
while ((new class ($rows) {
    public function __construct(private array $rows)
    {
    }

    public function go(): bool
    {
        for ($i = 0; $i < count($this->rows); $i++) {
            echo $i;
        }

        return true;
    }
})->go()) {
    echo 'outer';
}

// An arrow function has no braces of its own, so a loop reaches it only through
// a closure it returns.
while ((static fn (): bool => (function () use ($rows) {
    while (count($rows) > 0) {
        array_pop($rows);
    }

    return true;
})())()) {
    echo 'outer';
}

// A match arm is an expression, so a loop reaches it the same way.
while (match ($key) {
    'rows' => (function () use ($rows) {
        for ($i = 0; $i < count($rows); $i++) {
            echo $i;
        }

        return true;
    })(),
    default => false,
}) {
    echo 'outer';
}

// Two calls in one nested loop are two separate violations: the header is the
// nested loop's to report, the body is the outer scan's, and neither takes the
// other's.
while (someFn(function () use ($rows, $columns) {
    for ($i = 0; $i < count($rows); $i++) {
        echo count($columns);
    }

    return true;
})()) {
    echo 'outer';
}

// A nested loop owns its condition and nothing else. Its initialiser runs once
// per closure call and its increment once per nested pass, but the closure is
// called afresh every time the outer condition is tested, so both re-count —
// and the nested loop's own pass ignores both, by the same section rule that
// makes them silent at the top level. They are the enclosing scan's to report.
while (someFn(function () use ($rows) {
    for ($i = count($rows); $i > 0; $i--) {
        echo $i;
    }

    return true;
})()) {
    echo 'outer';
}

while (someFn(function () use ($rows) {
    for ($i = 0; $i < 3; $i += count($rows)) {
        echo $i;
    }

    return true;
})()) {
    echo 'outer';
}

// Skipping a nested condition must not consume the outer header's own section
// separators. The nested for sits in the initialiser, and the violation is in
// the outer condition after it — a jump that swallowed a separator would shift
// the section boundary and report nothing here.
for ($i = 0, $walk = function () use ($rows) {
    for ($j = 0; $j < 3; $j++) {
        echo $j;
    }

    return 1;
}; $i < count($items); $i++) {
    echo $walk();
}
