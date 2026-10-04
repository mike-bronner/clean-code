<?php

declare(strict_types=1);

class ArrayAccessorsElementAfterProperty
{
    public function readsElementsBehindProperties(object $order, array $payload, string $name): array
    {
        return [
            $order->tags['primary'],
            $order?->tags['primary']['label'],
            $order->tags['primary']->label,
            $order->customer->tags['primary'],
            $order->{$name}['primary'],
            self::$registry->tags['primary'],
            $payload['order']->customer->name,
        ];
    }
}
