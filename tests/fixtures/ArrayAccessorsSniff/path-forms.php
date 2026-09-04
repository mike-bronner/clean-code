<?php

declare(strict_types=1);

/**
 * One read per path form the fixer can emit, so the boundary between the dotted
 * string and the array literal is pinned shape by shape.
 *
 * The dotted form is only safe when every segment is identifier-shaped. A key
 * carrying a dot is the case that makes this load-bearing rather than cosmetic:
 * `data_get($payload, 'k.with.dot')` parses as three segments and silently
 * reads the wrong thing, while `data_get($payload, ['k.with.dot'])` reads the
 * key that was written. `data_get()` splits a string key on `.` and takes an
 * array key as literal segments, which is what separates the two.
 */
class ArrayAccessorsPathForms
{
    public function reads(array $payload, object $order, string $field, int $index): array
    {
        return [
            $payload['plain'],
            $payload['address']['city'],
            $payload['k.with.dot'],
            $payload['has space'],
            $payload['has-dash'],
            $payload[''],
            $payload[0],
            $payload[$index],
            $order->reference,
            $order->{$field},
        ];
    }
}
