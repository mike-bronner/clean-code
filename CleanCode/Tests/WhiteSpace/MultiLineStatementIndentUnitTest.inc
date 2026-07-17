<?php

// Positive: multi-line method chain.
$result = $queryBuilder
    ->select('*')
    ->from('users')
    ->where('active = 1');

// Positive: multi-line array literal.
$config = [
    'first' => 1,
    'second' => 2,
];

// Positive: multi-line conditional.
if ($first === 1
    && $second === 2
    || $third === 3
) {
    $matched = true;
}

// Positive: multi-line function call.
doSomething(
    $first,
    $second,
    $third
);

// Positive: nested multi-line array inside a multi-line call.
processData(
    $input,
    [
        'flag' => true,
        'mode' => 'strict',
    ]
);

// Positive: multi-line string concatenation.
$message = 'first part'
    . 'second part'
    . 'third part';

// Positive: multi-line ternary.
$label = $isActive
    ? 'active'
    : 'inactive';

// Positive: multi-line chain nested inside a multi-line call.
run(
    $builder
        ->prepare()
        ->execute()
);

// Positive: statement at a deeper base indent.
function buildLabel(bool $isActive): string
{
    $label = $isActive
        ? 'active'
        : 'inactive';

    return $label;
}

// Positive: closure body lines are scope-indented, not continuation lines.
$mapped = array_map(
    function ($item) {
        return $item * 2;
    },
    $items
);

// Negative: under-indented and misaligned chain lines.
$result = $queryBuilder
->select('*')
  ->from('users');

// Negative: over-indented array item and closer.
$config = [
        'first' => 1,
    'second' => 2,
        ];

// Negative: over-indented condition line.
if ($first === 1
        && $second === 2
) {
    $matched = true;
}

// Negative: under-indented call arguments.
doSomething(
$first,
    $second
);

// Negative: un-indented concatenation continuation.
$message = 'first part'
. 'second part';

// Negative: over-indented ternary branches.
$label = $isActive
        ? 'active'
        : 'inactive';

// Negative: under-indented continuation inside a function.
function combineParts(): string
{
    return 'first part'
    . 'second part';
}

// Negative: nested chain continuation at the wrong depth.
run(
    $builder
    ->prepare()
        ->execute()
);

// Negative: misaligned closing paren of a multi-line call.
doSomething(
    $first,
    $second
  );

// Positive: standalone heredoc.
$sql = <<<SQL
SELECT *
SQL;

// Positive: standalone nowdoc.
$text = <<<'TXT'
raw content
TXT;

// Negative: under-indented heredoc opener inside a multi-line call.
run(
<<<SQL
SELECT *
SQL,
);

// Positive: attribute on a multi-line function declaration.
#[Route('/home')]
function home(
    int $first,
) {
    return $first;
}

// Negative: under-indented parameter after an attribute.
#[Route('/away')]
function away(
$second,
) {
    return $second;
}

// Positive: comment on its own continuation line.
doSomething(
    // explains the flag
    $flag,
);

// Negative: a comment must not exempt the following line.
doSomething(
    // a comment
$wronglyIndented,
);

// Positive: multi-line match subject.
$firstLabel = match (
    $state
) {
    default => 'unknown',
};

// Negative: under-indented multi-line match subject.
$secondLabel = match (
$state
) {
    default => 'unknown',
};

// Negative: misindented nested multi-line array inside a multi-line call.
processData(
    $input,
    [
    'flag' => true,
        'mode' => 'strict',
],
);
