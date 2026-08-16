<?php

$name = 'Ada';

$dq = "Hello, line one
line two";

$interp = "Hi $name
welcome";

$quote = "she said \"hi\"
bye";

$sq = 'raw one
raw two';

$esc = 'it\'s a\\b
next';

$escapes = "tab\there\\slash
TEXTUAL context
and \$notvar";

$literal = 'keep \n and \t
literally';

// Load-bearing: an uppercase binary-string prefix stays inside the literal's
// token, so the token's first character is `B` rather than the delimiter.
// Reading the delimiter off it never matched `'`, so a single-quoted literal
// took the interpolating HEREDOC branch, lost its prefix, and kept its real
// opening quote in the body — a value one byte longer than the original.
$binarySingle = B'raw one
raw two';

// The double-quoted half of that prefix, so the branch the defect wrongly
// reached is pinned as the one such a literal genuinely belongs on. The escape
// resolves, which only the HEREDOC branch does.
$binaryDouble = B"she said \"hi\"
bye";
