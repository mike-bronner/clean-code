<?php

// Semicolons nested inside a for loop's *initialiser* are not section
// separators. In every loop below the count() call sits in the initialiser,
// after a nested semicolon, and the condition is a plain variable comparison.
//
// A separator scan that ignores nesting depth — findNext for the first
// semicolon and findPrevious for the last, the way
// Squiz.PHP.DisallowSizeFunctionsInLoops does it — treats the nested semicolon
// as the end of the initialiser, so the count() calls below land in what it
// believes is the condition and get reported. Depth-aware counting keeps them
// in the initialiser, where they belong, and the file stays silent.

// A closure body brace carries the nested semicolon.
for ($i = 0, $fn = function (): int {
    $seed = 1;
    return count([$seed]);
}; $i < $limit; $i++) {
    echo $fn();
}

// An argument list carries it instead of a brace body.
for ($i = 0, $sorters = array_map(static function (int $n): int {
    $doubled = $n * 2;
    return sizeof([$doubled]);
}, $numbers); $i < $limit; $i++) {
    echo $i;
}

// Two nested semicolons before the count(), so a scan that skipped only the
// first one would still land wrong.
for ($i = 0, $build = function (): int {
    $first = 1;
    $second = 2;
    return count([$first, $second]);
}; $i < $limit; $i++) {
    echo $build();
}
