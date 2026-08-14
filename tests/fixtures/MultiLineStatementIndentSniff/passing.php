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

// The other two chain operators anchor the way `->` does. Inside a call that
// is the receiver's own line, so both hang a level below it rather than below
// the opener — the layout that tells the two anchors apart.
run(
    $user
        ?->getProfile()
        ?->getName()
);

run(
    SomeFactory
        ::make('first')
        ->build()
);

// An index access is a bracket of its own, distinct from a short array: a
// nested one anchors its contents on itself, not on the call around it.
processData(
    $data[
        $key
    ]
);

// The keyword forms of the boolean operators are siblings exactly as `&&` and
// `||` are. The opener is alone on its line because that is the only layout
// where the sibling and continuation anchors differ.
if (
    $first === 1
    and $second === 2
    or $third === 3
    xor $fourth === 4
) {
    $matched = true;
}

// `instanceof` continues the expression above it, so inside a call it hangs
// below the argument's own line.
check(
    $subject
        instanceof Probe,
    $context
);

// A backtick string is the one quoted string PHPCS still splits into
// T_ENCAPSED_AND_WHITESPACE, so its tail lines are raw content like a
// heredoc body.
$output = `echo one
echo two`;

// Interpolation only changes which token PHPCS splits the string into —
// T_DOUBLE_QUOTED_STRING rather than T_CONSTANT_ENCAPSED_STRING — not that
// the tail lines are the string's own value.
report(
    "a message that runs {$user->name}
across two lines",
    $context
);

// An attribute group in a parameter list is a bracket inside the statement
// rather than the statement's own start, so its contents anchor on it and not
// on the parameter list around it.
function decorated(
    #[
        Route('/away'),
    ]
    int $second,
) {
    return $second;
}

// A comment never exempts the line it opens: the code sharing that line is
// checked, at the indent the comment sits at.
doSomething(
    /* explains the flag */ $flag,
);

// A comment that runs onto the line below carries its own body onto that line,
// so what precedes the code there is the comment rather than the line's indent.
// Neither that line nor the comment's opening line is measured here.
doSomething(
    /* explains the flag
       across two lines */ $first,
);

// A doc comment splits into one token per physical line the same way, so its
// tail line is the comment's too.
doSomething(
    /** explains the flag
     * across two lines */ $second,
);

// A body line whose own text begins with a slash pair opens no comment: the
// comment above it is still open, so the line stays the comment's.
doSomething(
    /* explains the flag
       // and says a little more */ $third,
);

// A null-coalescing operator continues the operand above it, so it hangs one
// level below that operand's line rather than below the call.
report(
    $override
        ?? $fallback,
    $context
);

// A wrapped key's `=>` leading its line continues that key, one level below it.
$routes = [
    'a very long descriptive key whose value will not fit beside it'
        => 'App\Http\Controllers\HomeController',
];

// A comment whose opening line is a bare slash-star-slash opens and does not
// close: those three characters only look like both ends at once.
doSomething(
    /*/
       explains the flag */ $fourth,
);

// A line that opens inside a comment has no indent of its own, so a
// continuation below it hangs from the line the comment opened.
report(
    /* explains the message
       across two lines */ 'a message long enough to run '
        . 'onto a second line',
    $context
);

// A statement starting on such a line reads its own base indent there too.
function annotated(): void
{
    /* explains the call
       across two lines */ report(
        $context
    );
}

// `||` is a sibling operator exactly as `&&` is: with the opener alone on its
// line — the one layout where the two anchors differ — it sits a level in from
// the line `if (` opens on rather than from the condition above it.
if (
    $first === 1
    || $second === 2
) {
    $matched = true;
}

// A ternary's branches continue the operand above them, so inside a call they
// hang below that operand's own line rather than below the opener.
report(
    $isActive
        ? 'active'
        : 'inactive',
    $context
);

// A call nested inside a call is a bracket of its own: its arguments anchor on
// the line it opens on rather than on the statement's.
outer(
    inner(
        $value
    )
);
