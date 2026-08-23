<?php

// Positive: distinct string keys, written with both quote styles.
$labels = [
    'name' => 'Name',
    "email" => 'Email',
    'e-mail' => 'Alternate',
];

// Positive: distinct integer keys across every base PHP accepts —
// 0x10 is 16, 0b101 is 5, 0o21 and 017 are 17 and 15, 1_000 is 1000.
$codes = [
    0x10 => 'hex',
    0b101 => 'binary',
    0o21 => 'modern octal',
    017 => 'legacy octal',
    1_000 => 'separated',
    -1 => 'negative',
];

// Positive: keys PHP coerces, chosen so that no two land on the same slot —
// false is 0, true is 1, and null is the empty string.
$flags = [
    false => 'off',
    true => 'on',
    null => 'unset',
    2 => 'two',
    'other' => 'named',
];

// Positive: only a canonical decimal string becomes an integer key, so '1' is
// the integer key 1 while '01', '1.0', and ' 1' stay distinct string keys.
$mixed = [
    '01' => 'leading zero',
    '1.0' => 'decimal string',
    ' 1' => 'leading space',
    '1' => 'canonical',
    2 => 'integer',
];

// Positive: a nested array scopes its keys to itself, so the inner 'id' and
// the outer 'id' are keys of two different arrays.
$row = [
    'id' => 1,
    'meta' => [
        'id' => 2,
        'name' => 'row',
    ],
];

// Positive: implicit (auto-incrementing) keys are not tracked — no literal in
// the source names them, because their values depend on every element before.
$list = ['first', 'second', 'third'];

// Positive: a spread element contributes no literal key of its own.
$merged = [...$list, 'extra' => 'value'];

// Positive: an arrow function's arrow is T_FN_ARROW, not the T_DOUBLE_ARROW
// that separates a key from a value, so these elements have no key at all.
$callbacks = [fn (): int => 1, fn (): int => 2];

// Positive: the long form and a keyed destructuring pattern are array literals
// too, and their distinct keys are left alone.
$long = array('alpha' => 1, 'beta' => 2);
['alpha' => $alpha, 'beta' => $beta] = $long;

// Positive: a key whose value is not in the token stream is skipped rather
// than guessed at, so even a repeated one is not reported.
$unresolved = [
    SOME_CONSTANT => 'a',
    SOME_CONSTANT => 'b',
    self::KEY => 'c',
    self::KEY => 'd',
    $name => 'e',
    $name => 'f',
    1 + 1 => 'g',
    1 + 1 => 'h',
    "tab\there" => 'i',
    "tab\there" => 'j',
    "{$prefix}_id" => 'k',
    "{$prefix}_id" => 'l',
    -'m' => 'n',
    -'m' => 'o',
];

// Positive: a float literal too large to be finite has no defined integer
// form, so it is left unresolved rather than folded onto whatever (int) INF
// happens to produce.
$infinite = [
    1e400 => 'a',
    1e400 => 'b',
];

// Positive: an integer literal that leaves the integer range while naming its
// digits in another base. Each pair below is a real duplicate — PHP stores both
// entries under one key — and the sniff declines all of them, because no
// conversion available to it answers the key PHP uses: the cast reads
// 0x8000000000000000 as 0.0 and 01000000000000000000000 as 1.0E+21, and
// hexdec(), bindec() and octdec() round these differently from PHP's own
// lexer. Silence is the deliberate answer; a key that is merely close would be
// a report against a slot PHP never used.
$hexadecimalOverflow = [
    0x8000000000000000 => 'a',
    0x8000000000000000 => 'b',
    -0x8000000000000000 => 'c',
    -0x8000000000000000 => 'd',
];

// 0b1 followed by 63 zeros is the case that rules bindec() out on its own: PHP
// evaluates it as 9223372036854774784, which is inside the integer range,
// while bindec() of the same digits is 9223372036854775808, which wraps onto
// PHP_INT_MIN.
$binaryOverflow = [
    0b1000000000000000000000000000000000000000000000000000000000000000 => 'a',
    0b1000000000000000000000000000000000000000000000000000000000000000 => 'b',
    -0b1000000000000000000000000000000000000000000000000000000000000000 => 'c',
    -0b1000000000000000000000000000000000000000000000000000000000000000 => 'd',
];

$octalOverflow = [
    0o1000000000000000000000 => 'a',
    0o1000000000000000000000 => 'b',
    -0o1000000000000000000000 => 'c',
    -0o1000000000000000000000 => 'd',
];

// The older octal spelling is the one the bare cast gets wrong quietly rather
// than loudly: it reads these digits as the decimal 1.0E+21 instead of 2**63.
$legacyOctalOverflow = [
    01000000000000000000000 => 'a',
    01000000000000000000000 => 'b',
    -01000000000000000000000 => 'c',
    -01000000000000000000000 => 'd',
];
