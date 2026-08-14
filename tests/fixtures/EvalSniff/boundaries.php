<?php

declare(strict_types=1);

namespace App;

class Interpreter
{
    private ?Calculator $calculator = null;

    public function run(string $expression): mixed
    {
        $calculator = $this->calculator;

        return $calculator->eval($expression);
    }

    public function runNullsafe(string $expression): mixed
    {
        $calculator = $this->calculator;

        return $calculator?->eval($expression);
    }

    public function runStatic(string $expression): mixed
    {
        return Calculator::eval($expression);
    }
}
