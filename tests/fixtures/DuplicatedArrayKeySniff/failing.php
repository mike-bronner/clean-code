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

// The wrap's own boundaries, each reached by two literals that are not the
// same text, so an error at the boundary cannot corrupt both entries into
// agreeing by accident. 2**63 is the first magnitude the direct cast cannot
// take, and 3 * 2**63 reaches the same key from twice the distance.
$boundaries = [
    9223372036854775808 => 'a',
    27670116110564327424 => 'b',
];

// 2**64 is a whole turn of the wrap, so it lands on 0 — the key the plain
// literal above it already holds. A modulus that was not 2**64 would put it
// somewhere else and there would be no duplicate here at all.
$modulus = [
    0 => 'a',
    18446744073709551616 => 'b',
];

// -2**63 is the one key whose own negation is not an integer, so a negated
// float is resolved with its sign rather than negated afterwards. Both
// literals land on PHP_INT_MIN, which is where PHP itself puts them.
$negatedBoundaries = [
    -9223372036854775808 => 'a',
    -27670116110564327424 => 'b',
];

// A remainder below -2**63 is lifted into [0, 2**64) before it is folded, so
// -2e30 lands on a positive key — the one the plain literal above it holds.
$lifted = [
    8292815763849347072 => 'a',
    -2.0e30 => 'b',
];

// A leading zero does not make a float literal octal, and the two spellings
// below are why the base is read off the digits rather than off the zero:
// `0.5` and `0e5` are decimal, and both land on the key 0 the plain literal
// above them already holds. Taking the zero alone for an octal marker leaves
// all three unresolved and reports nothing here at all.
$leadingZeroDecimals = [
    0 => 'a',
    0.5 => 'b',
    0e5 => 'c',
];

// The same shape with an octal digit in front of the period. `05.5` is 5.5, so
// it lands on key 5 — a check that stopped reading at the first character no
// octal digit can be would take it for the octal 05 and decline it.
$leadingZeroOctalDigits = [
    5 => 'a',
    05.5 => 'b',
];

// A digit separator is removed before the digits are read, in every base, so
// each of these is 15 again.
$separatedBases = [
    15 => 'a',
    0x0_F => 'b',
    0b1_111 => 'c',
    0o1_7 => 'd',
    01_7 => 'e',
];
