<?php

declare(strict_types=1);

namespace App;

class Calculator
{
    public function apply(int $param): int
    {
        if ($param === 42) {
            eval('$param = 23;');
        }

        return $param;
    }

    public function evaluate(string $expression): mixed
    {
        return eval('return ' . $expression . ';');
    }

    public function bootstrap(string $source): void
    {
        eval($source);
    }
}
