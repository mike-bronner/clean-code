<?php

// Divergence — stricter than PHPMD. PHPMD compares key literals as source
// text, so `01`, `0x2`, and `1.9` look like different keys from `1`, `2`, and
// `1`. PHP resolves each pair to one integer key, so this sniff reports them.
$basesAndFloats = [
    1 => 'a',
    01 => 'b',
    2 => 'c',
    0x2 => 'd',
    1.9 => 'e',
];

// Divergence — stricter than PHPMD, which resolves no key that is not a bare
// literal and so never compares negative ones.
$negatives = [
    -1 => 'a',
    -1 => 'b',
];

// Divergence — PHPMD reports here and this sniff does not. Stripping the
// quotes makes '01' look like the octal literal 01 to PHPMD. PHP keeps '01' a
// string key, because only a canonical decimal string becomes an integer key.
$leadingZeroString = [
    01 => 'a',
    '01' => 'b',
];

// Divergence — scope. PHPMD's rule is method- and function-aware, so an array
// declared outside one is never inspected. This sniff registers on the array
// literal itself, wherever it appears.
const DEFAULTS = [
    'k' => 1,
    'k' => 2,
];
