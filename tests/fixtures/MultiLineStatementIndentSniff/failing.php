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

// An arrow function's body indented as a sibling argument instead of a
// continuation of the line its `fn` sits on. The two anchors differ here, so
// this fails unless T_FN_ARROW is read as a trailing operator.
$incremented = array_map(
    fn (int $value): int =>
    $value + 1,
    $numbers
);

// The same arrow leading its line, at the sibling depth instead.
$doubled = array_map(
    fn ($value)
    => $value * 2,
    $numbers
);

// An arrow function at statement level with an un-indented body. Only the
// `fn` exception in findStatementEnd() keeps the body inside the statement;
// without it the arrow ends the statement and both halves look single-line.
$callback = fn ($item) =>
$item * 2;

// An anonymous class body is skipped, but the argument after it is not.
$adapted = array_map(
    new class {
        public function map($item)
        {
            return $item * 2;
        }
    },
$items
);

// Only the *tail* lines of a multi-line string are content: the opening
// fragment is the argument, and an under-indented one still reports.
report(
"a message that runs
across two lines",
    $context
);

// The other two chain operators at the sibling depth instead of hanging below
// the receiver's own line.
run(
    $user
    ?->getProfile()
);

run(
    SomeFactory
    ::make('first')
);

// A nested index access anchored on the call around it instead of on its own
// bracket.
processData(
    $data[
    $key
    ]
);

// The keyword boolean operators are siblings of the first condition, so one
// level deeper than it is wrong for them too.
if (
    $first === 1
        and $second === 2
        or $third === 3
        xor $fourth === 4
) {
    $matched = true;
}

// `instanceof` indented as a sibling argument instead of a continuation of
// the argument above it.
check(
    $subject
    instanceof Probe,
    $context
);

// An under-indented backtick opener: the opener is code, even though the body
// it introduces is not.
run(
`echo one
echo two`,
);

// Only the tail lines of an interpolated string are content: the opening
// fragment is the argument, and an under-indented one still reports.
report(
"a message that runs {$user->name}
across two lines",
    $context
);

// An under-indented member of an attribute group nested in a parameter list.
function decorated(
    #[
    Route('/home'),
    ]
    int $first,
) {
    return $first;
}

// A comment does not exempt the line it shares with code either.
doSomething(
  /* explains the flag */ $flag,
);

// The opening line of a comment that runs onto the line below is the line that
// carries the indent, so it is the one that reports; the code sharing the
// comment's tail line reports nothing.
doSomething(
  /* explains the flag
     across two lines */ $first,
);

// A doc comment reports the same way, on its opening line.
doSomething(
  /** explains the flag
   * across two lines */ $second,
);

// A whole one-line comment sitting below another opens and closes its own, so
// the code after it is the line's own and still reports.
doSomething(
// a note that ends on its own line
/* explains the flag */ $third,
);

// A null-coalescing continuation indented as a sibling argument instead.
report(
    $override
    ?? $fallback,
    $context
);

// A wrapped key's leading `=>` at the sibling depth instead of the key's.
$routes = [
    'a very long descriptive key whose value will not fit beside it'
    => 'App\Http\Controllers\HomeController',
];

// A continuation below a line that opens inside a comment, at the sibling
// depth instead of the comment's own.
report(
    /* explains the message
       across two lines */ 'a message long enough to run '
    . 'onto a second line',
    $context
);

// A statement starting on such a line, with its argument a level short of the
// indent the comment's line sets.
function annotated(): void
{
    /* explains the call
       across two lines */ report(
    $context
    );
}
