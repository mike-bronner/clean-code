<?php

declare(strict_types=1);

namespace App\Billing;

class InvoiceFactory extends Factory
{
    public function make(string $class, array $classes, object $other, string $name): array
    {
        return [
            new Invoice(),
            new \App\Billing\Invoice(),
            new Billing\Invoice(),
            new namespace\Invoice(),
            new static(),
            new self(),
            new parent(),
            new $class(),
            new $classes['invoice'](),
            new $this->class(),
            new $this->$name(),
            new $this->{$name}(),
            new Invoice::$class(),
            new $classes['invoice']->class(),
            new (resolveClass())(),
            (new Invoice())->total(),
            new Invoice() == $other->build(),
            new Invoice( ),
            new Invoice (),
            new Invoice /* no arguments */ (),
            new Invoice
            (),
            send(new Invoice()),
        ];
    }
}
