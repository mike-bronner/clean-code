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

// A single-line statement has no continuation line to check.
$single = doSomething($first, $second);
