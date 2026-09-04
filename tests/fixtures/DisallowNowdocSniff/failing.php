<?php

// A NOWDOC's value is exactly its body, with no escape resolved and no
// expression interpolated. Every shape below is carried across to a HEREDOC
// with the same value, which is what makes the conversion safe to automate.
//
// $value is defined, and deliberately never substituted: each body below that
// names it must still read as the four characters `$val`… after the fix.
$value = 'INTERPOLATED';

// Plain text. Nothing in the body needs escaping, so only the quotes around
// the opening identifier go.
$plain = <<<'TEXT'
    first line
    second line
    TEXT;

// A bare `$name` is text in a NOWDOC and an interpolation in a HEREDOC, so it
// leaves as `\$name`.
$dollar = <<<'TEXT'
    the value is $value
    TEXT;

// `{$…}` and `${…}` are the two brace triggers. Escaping the `$` neutralises
// both — `{$` becomes `{\$` and `${` becomes `\${` — which is why a lone brace
// needs nothing of its own.
$braces = <<<'TEXT'
    brace form {$value} and dollar-brace form ${value}
    TEXT;

// A backslash is literal in a NOWDOC and opens an escape in a HEREDOC, so it
// doubles.
$backslash = <<<'TEXT'
    windows path C:\temp\new
    TEXT;

// `\n` is two characters in a NOWDOC and must stay two characters. This is the
// case a fixer that only stripped the quotes would silently turn into a
// newline.
$escapeSequence = <<<'TEXT'
    literal backslash-n: \n
    TEXT;

// A `"` needs no escape in a HEREDOC at all, and neither does a `'`. That is
// the readability gain a HEREDOC has over both quoted forms.
$quotes = <<<'TEXT'
    he said "hi" and 'bye'
    TEXT;
