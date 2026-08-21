<?php

// One chain per token a grouping parenthesis may follow, so that every member
// of the sniff's admission set — the hand-written list and each of the five
// PHP_CodeSniffer unions it defers to — is reported by a line of its own.
// Removing any one of them silences exactly its line here and nothing else.
//
// The comment above is skipped as whitespace, so the first statement's group
// is still preceded by the open tag itself.

($book)->author->name;

// The hand-written list: where an expression starts.

$statement = 1;
($book)->author->name;

if ($condition) {
    ($book)->author->name;
} else ($book)->author->name;

do ($book)->author->name; while ($condition);

label: ($book)->author->name;

// The hand-written list: openers and separators inside an expression.

$subscript = $rows[($book)->author->name];
$literal = [($book)->author->name];
$element = foo($first, ($book)->author->name);
$then = $condition ? ($book)->author->name : null;
$else = $condition ? null : ($book)->author->name;
$pair = [$key => ($book)->author->name];
$arrow = fn () => ($book)->author->name;
$arm = match (true) {
    default => ($book)->author->name,
};
$nested = foo(($book)->author->name);

// The hand-written list: keywords taking an unparenthesised expression.

echo ($book)->author->name;
print ($book)->author->name;
clone ($book)->author->name;
include ($book)->author->name;
include_once ($book)->author->name;
require ($book)->author->name;
require_once ($book)->author->name;
foo(...($book)->author->name);

switch ($value) {
    case ($book)->author->name:
        break;

    default:
        ($book)->author->name;
}

function reportName(): string
{
    return ($book)->author->name;
}

function failName(): void
{
    throw ($book)->author->error;
}

function generateName(): Generator
{
    yield ($book)->author->name;
}

function delegateName(): Generator
{
    yield from ($book)->author->name;
}

// The hand-written list: prefix operators and concatenation, which
// PHP_CodeSniffer's own unions leave out.

$not = !($book)->author->name;
$complement = ~($book)->author->name;
$suppressed = @($book)->author->name;
$joined = 'prefix ' . ($book)->author->name;

// One line per PHP_CodeSniffer union the admission set defers to, so that each
// deferral is load-bearing rather than assumed: assignment, arithmetic,
// comparison, boolean and cast, in that order.

$assigned = ($book)->author->name;
$sum = 1 + ($book)->author->name;
$same = 'x' === ($book)->author->name;
$both = $condition && ($book)->author->name;
$cast = (string) ($book)->author->name;

?>
<?= ($book)->author->name ?>
