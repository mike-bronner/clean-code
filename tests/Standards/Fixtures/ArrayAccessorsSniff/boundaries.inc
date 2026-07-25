<?php

declare(strict_types=1);

class ArrayAccessorsBoundaries
{
    private array $config = ['key' => 'value'];

    private int $counter = 0;

    public function writesElements(array $payload, object $order): array
    {
        $payload['name'] = 'set';
        $payload['address']['city'] = 'set';
        $payload['tags'][] = 'appended';
        $payload[] = 'appended';
        $payload['count'] += 1;
        $payload['label'] .= '-suffix';
        $payload['fallback'] ??= 'default';
        $order->reference = 'set';
        $order->customer->name = 'set';
        ++$payload['count'];
        $payload['count']--;

        return $payload;
    }

    public function checksExistence(array $payload, object $order): bool
    {
        if (isset($payload['name']) === true) {
            return true;
        }

        if (empty($payload['address']['city']) === true) {
            return false;
        }

        if (array_key_exists('city', $payload['address']) === true) {
            return true;
        }

        if (isset($order->reference) === true) {
            return true;
        }

        unset($payload['name']);

        return false;
    }

    public function declaresLiterals(string $value, object $order): array
    {
        return [
            'key' => $value,
            'nested' => ['inner' => $value],
            'list' => [$value, $value],
            'call' => $order->render(),
        ];
    }

    public function readsOwnState(): array
    {
        $whole = $this->config;
        $element = $this->config['key'];
        $counter = $this->counter;

        return [$whole, $element, $counter];
    }

    public function callsMethods(object $order, string $name): string
    {
        $reference = $order->reference();
        $city = $order?->address();
        $dynamic = $order->{$name}();

        return $reference . $city . $dynamic;
    }
}
