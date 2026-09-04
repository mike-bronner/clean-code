<?php

// The form this standard keeps. Every fixer in the package emits it, and a
// reader never has to check the opening identifier before trusting the body.
$plain = <<<TEXT
    first line
    second line
    TEXT;

// A HEREDOC that actually interpolates. Nothing here is a violation: the rule
// is about the quoted opening identifier, not about whether the body has a `$`.
$value = 'INTERPOLATED';
$interpolated = <<<TEXT
    the value is {$value}
    TEXT;

// Quoted strings are somebody else's concern. CleanCode.Strings.MultilineStrings
// owns the multi-line quoted form, and this sniff registers on T_START_NOWDOC
// alone, so a single-quoted literal must stay silent here.
$quoted = 'plain text';
