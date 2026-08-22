<?php

$name = 'Ada';

// PHP_CodeSniffer cannot tokenize an interpolated binary-prefixed string. It
// types the `B"` opener T_NONE and then mis-types the rest of the statement, so
// the sniff was handed a "string literal" that is really this string's closing
// quote plus the source below it — and rewrote that run into a HEREDOC, folding
// the next statement into the doc-string body.
$binaryInterpolated = B"Hi $name
welcome";

// The victim of that rewrite, and the reason the fixer's output has to be
// compared byte-for-byte rather than only parsed: the mangled file still passed
// php -l while meaning something else entirely.
$plainDouble = "Hi $name
welcome";
