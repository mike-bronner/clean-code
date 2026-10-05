<?php

declare(strict_types=1);

namespace App\Billing;

class InvoiceFactory extends Factory
{
    public function make(string $class, array $classes, object $other): array
    {
        return [
            new Invoice,
            new \App\Billing\Invoice,
            new Billing\Invoice,
            new namespace\Invoice,
            new Invoice($this->total),
            new Invoice()->total(),
            new Invoice()?->total,
            new Invoice()::create(),
            new Invoice()['total'],
            new Invoice()(),
            (new Invoice)->total(),
            new class () {
            },
            new class {
            },
            new $class,
            new $classes['invoice'],
            new static,
            new self,
            new parent,
            new (resolveClass()),
            new Invoice(/* nothing yet */),
            new Invoice == $other->build(),
            new Invoice()
                ->total(),
        ];
    }
}
