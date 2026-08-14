<?php

// Fixable: re-delimiting to double quotes cannot change the meaning.
$simple = 'He said "hi" to me';
$attribute = '<span title="tip">x</span>';

// Not fixable: a `$` would start interpolation once the literal is
// double-quoted, so the conversion is left to a human.
$withVariable = 'echo "$value" here';

// Not fixable: a backslash escape means something different between the two
// quote styles.
$withEscape = 'path "C:\\temp"';

// Not fixable: a brace opens interpolation under double quotes.
$withBrace = 'render "{name}" now';
