<?php

declare(strict_types=1);

// Deliberately not valid PHP: a function call is not an assignable target, so
// this is a parse error. PHP_CodeSniffer still tokenizes it, and the sniff must
// not let a malformed statement suppress a read it would otherwise report --
// only `list(...)` parentheses close a destructuring pattern, so the `=` after
// an ordinary call's `)` must not be read as assigning to the argument.
// Reporting on ambiguity is the safe direction for a linter.
$first = doSomething($payload['key']) = $default;
$second = doSomething($payload['key']);
