<?php

declare(strict_types=1);

namespace App;

// A goto at file scope, outside any function or method. PHPMD's own rule is
// MethodAware/FunctionAware and never reaches this; the sniff does.
$attempts = 0;

top:
$attempts++;

if ($attempts < 3) {
    goto top;
}

/**
 * PHPMD's own documented example for Design/GotoStatement.
 */
function phpmdExample(int $param): int
{
    if ($param === 42) {
        goto x;
    }

    x:
    return 42;
}

class Router
{
    public function nested(): void
    {
        foreach ([1, 2] as $outer) {
            while (true) {
                goto done;
            }
        }

        done:
        echo 'done';
    }

    public function twoJumpsOneLabel(int $value): void
    {
        if ($value === 1) {
            goto end;
        }

        if ($value === 2) {
            goto end;
        }

        end:
        echo 'end';
    }
}
