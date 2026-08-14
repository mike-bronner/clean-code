<?php

declare(strict_types=1);

class ArrayAccessorsPassing
{
    public function readsElements(array $payload): array
    {
        $name = data_get($payload, 'name');
        $city = data_get($payload, 'address.city');
        $first = data_get($payload, 0);

        return [$name, $city, $first];
    }

    public function readsProperties(object $order, object $customer): array
    {
        $reference = data_get($order, 'reference');
        $city = data_get($order, 'address.city');
        $email = data_get($customer, 'email', 'unknown@example.com');

        return [$reference, $city, $email];
    }

    public function readsInExpressions(array $payload, object $order): bool
    {
        if (data_get($payload, 'enabled') === true) {
            return true;
        }

        return data_get($order, 'status') === data_get($payload, 'status');
    }

    public function readsNestedShapes(array $payload): string
    {
        return strtoupper(data_get($payload, 'items.0.name', ''));
    }
}
