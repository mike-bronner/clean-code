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

// A literal spanning several physical lines is tokenized one token per line.
// Only the first fragment opens with the delimiter, and re-delimiting that
// fragment alone would leave the string unterminated — so the sniff stays
// silent even though the value carries double quotes.
$multiline = 'say "hi"
and "bye"';
