<?php

declare(strict_types=1);

// Under-indented and misaligned chain lines.
$result = $queryBuilder
->select('*')
  ->from('users');

// Over-indented array item, and a misaligned closer.
$config = [
        'first' => 1,
    'second' => 2,
        ];

// A value below a trailing `=>`, indented as if it started a new element.
$routes = [
    'a very long descriptive key whose value will not fit beside it' =>
    'App\Http\Controllers\HomeController',
];

// Boolean operands are siblings of the first condition, so one level deeper
// than it is wrong — with the opener shared, and with it alone on its line.
if ($first === 1
        && $second === 2
) {
    $matched = true;
}

if (
    $first === 1
        && $second === 2
) {
    $matched = true;
}

// Under-indented call arguments.
doSomething(
$first,
    $second
);

// Un-indented concatenation continuation.
$message = 'first part'
. 'second part';

// Concatenation inside a call, indented as a sibling argument instead of a
// continuation of the argument above it.
report(
    'a message long enough to run '
    . 'onto a second line',
    $context
);

// Arithmetic continuation indented as a sibling operand.
$total = calculate(
    $first
    + $second
);

// Over-indented ternary branches.
$label = $isActive
        ? 'active'
        : 'inactive';

// Under-indented continuation inside a function.
function combineParts(): string
{
    return 'first part'
    . 'second part';
}

// A nested chain continuation at the wrong depth.
run(
    $builder
    ->prepare()
        ->execute()
);

// A misaligned closing paren.
doSomething(
    $first,
    $second
  );

// A misindented nested array inside a multi-line call.
processData(
    $input,
    [
    'flag' => true,
        'mode' => 'strict',
],
);

// An under-indented heredoc opener inside a multi-line call: the opener is
// code, even though the body it introduces is not.
run(
<<<SQL
SELECT *
SQL,
);

// The same for a nowdoc.
run(
<<<'TXT'
raw content
TXT,
);

// An under-indented parameter after an attribute.
#[Route('/away')]
function away(
$second,
) {
    return $second;
}

// A misaligned closer on a multi-line attribute group.
#[
    Route('/home'),
  ]
function home(
    int $first,
) {
    return $first;
}

// A comment does not exempt the line after it.
doSomething(
    // a comment
$wronglyIndented,
);

// An under-indented multi-line match subject.
$secondLabel = match (
$state
) {
    default => 'unknown',
};
