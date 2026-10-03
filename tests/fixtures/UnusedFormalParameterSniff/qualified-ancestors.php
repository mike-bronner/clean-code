<?php

declare(strict_types=1);

// Child names its parent and its interface through qualified names, which
// PHP_CodeSniffer 4 reads as one token each. The override exemption still finds
// both same-file ancestors, so every declaration here is silent.

namespace App\Billing;

interface Contract
{
    public function describe(string $label): string;
}

class Origin
{
    public function handle(string $payload): string
    {
        return $payload;
    }
}

class Child extends \App\Billing\Origin implements namespace\Contract
{
    public function handle(string $payload): string
    {
        return 'handled';
    }

    public function describe(string $label): string
    {
        return 'described';
    }
}
