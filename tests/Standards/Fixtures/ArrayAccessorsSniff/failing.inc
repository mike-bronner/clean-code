<?php

declare(strict_types=1);

class ArrayAccessorsFailing
{
    public function readsElements(array $payload): array
    {
        $name = $payload['name'];
        $city = $payload['address']['city'];
        $first = $payload[0];

        return [$name, $city, $first];
    }

    public function readsProperties(object $order, object $customer): array
    {
        $reference = $order->reference;
        $city = $order->address->city;
        $email = $customer?->email;

        return [$reference, $city, $email];
    }

    public function readsInExpressions(array $payload, object $order): bool
    {
        if ($payload['enabled'] === true) {
            return true;
        }

        return $order->status === $payload['status'];
    }

    public function readsNestedShapes(array $payload): string
    {
        $name = $payload['items'][0]->name;

        return strtoupper($name);
    }

    public function readsThroughIndirection(object $order, string $field): array
    {
        $translated = $order->$field['locale'];
        $registered = self::$registry['key'];
        $dynamic = $order->{$field};

        return [$translated, $registered, $dynamic];
    }
}
