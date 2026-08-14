<?php

// The canonical form: double-quoted, inner quotes escaped.
$escaped = "He said \"hi\"";

// Near misses. A single-quoted literal is only a violation when it carries a
// double quote, so these must all stay silent.
$singleNoQuotes = 'plain text';
$doubleWithSingle = "it's fine";
$empty = '';
$interpolated = "value: {$x}";
$escapedApostrophe = 'it\'s fine too';
