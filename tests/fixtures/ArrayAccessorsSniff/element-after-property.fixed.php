<?php

declare(strict_types=1);

class ArrayAccessorsElementAfterProperty
{
    public function readsElementsBehindProperties(object $order, array $payload, string $name): array
    {
        return [
            data_get($order->tags, 'primary'),
            data_get($order?->tags, 'primary.label'),
            data_get($order->tags, 'primary.label'),
            data_get($order->customer->tags, 'primary'),
            data_get($order->{$name}, 'primary'),
            data_get(self::$registry->tags, 'primary'),
            data_get($payload, 'order.customer.name'),
        ];
    }
}
