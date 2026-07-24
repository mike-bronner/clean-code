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
