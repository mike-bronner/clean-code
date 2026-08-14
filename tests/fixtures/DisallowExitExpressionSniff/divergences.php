<?php

// The two shapes where this sniff is stricter than PHPMD's ExitExpression.
// Both are genuine exit expressions inside a method or function, so both are
// reported rather than suppressed to match PHPMD's silence.
//
// 1. A method of an anonymous class declared at file scope.
//
// PDepend, which PHPMD parses with, does not model a method of an anonymous
// class declared at file scope as a method, so PHPMD reports nothing here —
// verified against PHPMD 2.15.0. The same anonymous class written inside a
// named method *is* reported by both tools (failing.php pins that), which is
// what makes this a parser blind spot rather than a deliberate carve-out.
//
// 2. The fully qualified `\exit` / `\die` spelling.
//
// PHPMD reports nothing on either; PHPCS keeps the leading-backslash form as
// T_EXIT on purpose, because it is still the construct and not a function.

$abort = new class {
    public function boom(): void
    {
        exit(1);
    }
};

$fail = new class {
    public function halt(): void
    {
        die('anonymous');
    }
};

function fullyQualifiedExit(): void
{
    \exit(1);
}

function fullyQualifiedDie(): void
{
    \die('qualified');
}
