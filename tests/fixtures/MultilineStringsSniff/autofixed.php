<?php

$name = 'Ada';

$dq = <<<TEXT
Hello, line one
line two
TEXT;

$interp = <<<TEXT
Hi $name
welcome
TEXT;

$quote = <<<TEXT
she said "hi"
bye
TEXT;

$sq = <<<'TEXT'
raw one
raw two
TEXT;

$esc = <<<'TEXT'
it's a\b
next
TEXT;

$escapes = <<<TEXT
tab\there\\slash
TEXTUAL context
and \$notvar
TEXT;

$literal = <<<'TEXT'
keep \n and \t
literally
TEXT;

// Load-bearing: an uppercase binary-string prefix stays inside the literal's
// token, so the token's first character is `B` rather than the delimiter.
// Reading the delimiter off it never matched `'`, so a single-quoted literal
// took the interpolating HEREDOC branch, lost its prefix, and kept its real
// opening quote in the body — a value one byte longer than the original.
$binarySingle = B<<<'TEXT'
raw one
raw two
TEXT;

// The double-quoted half of that prefix, so the branch the defect wrongly
// reached is pinned as the one such a literal genuinely belongs on. The escape
// resolves, which only the HEREDOC branch does.
$binaryDouble = B<<<TEXT
she said "hi"
bye
TEXT;
