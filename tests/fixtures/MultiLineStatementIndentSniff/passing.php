<?php

declare(strict_types=1);

// A chain hangs one level below the line its expression starts on.
$result = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('active = 1');

// Array items sit one level in from the line the bracket opens on, and the
// closer returns to that line's indent.
$config = [
    'first' => 1,
    'second' => 2,
];

// A trailing `=>` leaves its element open, so the value continues that
// element rather than starting a new one.
$routes = [
    'a very long descriptive key whose value will not fit beside it' =>
        'App\Http\Controllers\HomeController',
];

// Boolean operators join sibling conditions, so each sits one level in from
// the line `if (` opens on — whether or not the first condition shares it.
if ($first === 1
    && $second === 2
    || $third === 3
) {
    $matched = true;
}

if (
    $first === 1
    && $second === 2
) {
    $matched = true;
}

// Arguments sit one level in from the opener's line.
doSomething(
    $first,
    $second,
    $third
);

// A nested bracket anchors its own items on its own opener.
processData(
    $input,
    [
        'flag' => true,
        'mode' => 'strict',
    ]
);

// Concatenation continues a single expression, so it hangs one level below
// the line that expression started on.
$message = 'first part'
    . 'second part'
    . 'third part';

// Inside a call that is the argument's own line, not the opener's.
report(
    'a message long enough to run '
        . 'onto a second line',
    $context
);

// Arithmetic continues an expression the same way.
$total = calculate(
    $first
        + $second
        - $third
);

// Ternary branches continue the expression above them.
$label = $isActive
    ? 'active'
    : 'inactive';

// A chain nested inside a multi-line call.
run(
    $builder
        ->prepare()
        ->execute()
);

// The same rules at a deeper base indent.
function buildLabel(bool $isActive): string
{
    $label = $isActive
        ? 'active'
        : 'inactive';

    return $label;
}

// A closure body is a scope block, so scope-indent rules own its inner
// lines; the argument list around it is still checked.
$mapped = array_map(
    function ($item) {
        return $item * 2;
    },
    $items
);

// Heredoc and nowdoc bodies are raw content, never indentation.
$sql = <<<SQL
SELECT *
SQL;

$text = <<<'TXT'
raw content
TXT;

// An attribute is a construct of its own: the declaration it decorates
// starts a fresh statement.
#[Route('/home')]
function home(
    int $first,
) {
    return $first;
}

// A multi-line attribute group closes on the line it opened on.
#[
    Route('/away'),
]
function away(
    int $second,
) {
    return $second;
}

// A comment sits on a continuation line like any other token.
doSomething(
    // explains the flag
    $flag,
);

// A match subject belongs to the statement; the arms are a scope block.
$firstLabel = match (
    $state
) {
    default => 'unknown',
};

// An arm's `=>` is T_MATCH_ARROW, the third of the three arrow tokens. It is
// never read as a trailing operator because the arms are a scope block that
// is skipped whole — so a wrapped arm body is not this sniff's to measure,
// whatever depth it sits at.
$secondLabel = match ($state) {
    'first' =>
            'the first state',
    default => 'unknown',
};

// A single-line statement has no continuation line to check.
$single = doSomething($first, $second);

// An arrow function's `=>` is T_FN_ARROW, not T_DOUBLE_ARROW, but trails the
// same way: its body continues the line the `fn` sits on. The body is an
// expression, not a scope block, so it stays inside the statement.
$incremented = array_map(
    fn (int $value): int =>
        $value + 1,
    $numbers
);

// The same arrow, leading its line instead of trailing the one above.
$doubled = array_map(
    fn ($value)
        => $value * 2,
    $numbers
);

// An arrow function at statement level, its body wrapped below the arrow.
$callback = fn ($item) =>
    $item * 2;

// An anonymous class body is a scope block like a closure's; the argument
// list around it is still checked.
$adapted = array_map(
    new class {
        public function map($item)
        {
            return $item * 2;
        }
    },
    $items
);

// PHPCS splits a quoted string that spans lines into one token per physical
// line. Those tail lines are the string's value, not code, so they are never
// measured — `CleanCode.Strings.MultilineStrings` is what forbids this shape.
// The opening fragment is still the argument, and is indented as one.
report(
    "a message that runs
across two lines",
    $context
);
