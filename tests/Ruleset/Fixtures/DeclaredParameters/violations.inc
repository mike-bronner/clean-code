<?php

declare(strict_types=1);

namespace Acme\Violations;

class Router
{
    public function dispatch(): array
    {
        $args = func_get_args();

        if (func_num_args() > 1) {
            return [func_get_arg(1)];
        }

        return $args;
    }

    public function qualified(): array
    {
        return \func_get_args();
    }

    public function shouted(): array
    {
        return FUNC_GET_ARGS();
    }
}

// A call at file scope sits in no function at all: there is no declaration to
// carry the magic-method exemption, so it is still a dynamic argument read.
$args = func_get_args();
