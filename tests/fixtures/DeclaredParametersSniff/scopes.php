<?php

declare(strict_types=1);

namespace Acme\Scopes;

class Handler
{
    public function build(): callable
    {
        return function () {
            return func_get_args();
        };
    }

    public function __call(string $name, array $arguments): callable
    {
        // A closure inside a magic method declares its own parameter list, so
        // it does not inherit the exemption.
        return function () {
            return func_get_args();
        };
    }

    public function __invoke(): callable
    {
        // Neither does an arrow function.
        return fn (): array => func_get_args();
    }

    public function __get(string $name): array
    {
        // The magic method's own body keeps the exemption even when an arrow
        // function is declared alongside the call.
        $ignored = fn (): int => 1;

        return func_get_args();
    }
}

// A plain function named like a magic method is not one.
function __get(): array
{
    return func_get_args();
}

class Container
{
    public function register(): void
    {
        // Magic methods belong to an OO container, not to another function's
        // body: a function declared inside a method is a plain function, so
        // being named like a magic method earns it no exemption.
        function __set(): array
        {
            return func_get_args();
        }
    }
}
