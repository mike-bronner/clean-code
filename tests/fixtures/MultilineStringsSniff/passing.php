<?php

// HEREDOC multi-line string (interpolating) — compliant.
$html = <<<HTML
    <div>
        Hello, world!
    </div>
    HTML;

// NOWDOC multi-line string (literal), indented closing marker — compliant.
$text = <<<'TEXT'
    plain
    literal
    TEXT;

// Inline SQL via HEREDOC — the shape the standard is really about.
$sql = <<<SQL
    SELECT id, name
    FROM users
    SQL;

// Single-line quoted strings are never flagged.
$single = "just one line";
$singleQuoted = 'also one line';

// A single-line SQL statement passed as a plain string is not flagged.
$query = "SELECT * FROM users WHERE id = 1";

// Single-line concatenation is not flagged.
$greeting = "Hello, " . $name . "!";
$parts = "a" . "b" . "c";

// A multi-line concatenation that mixes in a non-string operand (a function
// call or a variable) is a value-composing expression wrapped for line length,
// not a string literal split across lines — it is left alone.
$class = 'App\\Models\\'
    . ucfirst($model);
$label = "Total: "
    . $count
    . " items";

// The escape sequence \n keeps the string on one physical source line, so it
// is single-line and not flagged.
$escapedNewline = "line one\nline two";
