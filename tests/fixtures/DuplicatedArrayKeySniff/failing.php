<?php

// The example from the PHPMD rule documentation: 0 and false name the same
// key, and 'foo' and "foo" name the same key.
$phpmdExample = [
    0 => 'a',
    false => 'b',
    'foo' => 'bar',
    "foo" => 'baz',
];

// A canonical decimal string is the integer key PHP coerces it to, and true
// is the integer key 1.
$coerced = [
    1 => 'a',
    '1' => 'b',
    true => 'c',
];

// null is the empty-string key.
$nullKey = [
    null => 'a',
    '' => 'b',
];

// Every integer base names the same number: 15, 0xF, 0b1111, 017, and 1_5.
$bases = [
    15 => 'a',
    0xF => 'b',
    0b1111 => 'c',
    017 => 'd',
    1_5 => 'e',
];

// A float key is truncated toward zero, in both directions.
$floats = [
    1 => 'a',
    1.9 => 'b',
    -1 => 'c',
    -1.9 => 'd',
];

// A literal too large for the integer range arrives as a float, and PHP casts
// it to the same integer key both times.
$overflowing = [
    9223372036854775808 => 'a',
    9223372036854775808 => 'b',
];

// The first declaration is the one kept, so a key written three times is
// reported twice and both reports name the same line.
$repeated = [
    'k' => 1,
    'k' => 2,
    'k' => 3,
];

// The long form is the same literal.
$long = array('k' => 1, 'k' => 2);

// A nested array is checked in its own right, and so is the array holding it.
$nested = [
    'outer' => [
        'k' => 1,
        'k' => 2,
    ],
    'outer' => 3,
];

// A keyed destructuring pattern binds two variables from one key, which makes
// the second binding a copy of the first rather than a second value.
['k' => $first, 'k' => $second] = $long;

// A single-quoted string's two escape sequences each stand for the character
// after the backslash, so these are the keys `it's` and `a\b`, written twice
// apiece. A lone backslash before anything else is already literal.
$escaped = [
    'it\'s' => 'a',
    "it's" => 'b',
    'a\\b' => 'c',
    'a\b' => 'd',
];

// A finite float too large for the integer range is not saturated onto the
// range's end: PHP wraps it modulo 2**64, so two such literals are the same key
// only when they wrap to the same value. 1e30 and 2e30 land on different keys
// and are not duplicates; each literal written twice is.
$wrapped = [
    1.0e30 => 'a',
    2.0e30 => 'b',
    1.0e30 => 'c',
    -1.0e30 => 'd',
    -1.0e30 => 'e',
];
