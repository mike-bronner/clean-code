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

    public function destructuresIntoElements(array $source, object $order): array
    {
        [$payload['first'], $payload['second']] = $source;
        list($payload['third'], $payload['fourth']) = $source;
        [$order->first, $order->second] = $source;
        ['key' => $payload['keyed']] = $source;
        [[$payload['nested']]] = $source;

        return $payload;
    }

    public function bindsByReference(array $payload): array
    {
        $reference = &$payload['name'];
        $reference = 'set';

        return $payload;
    }

    public function assignsForeachTargets(array $rows): array
    {
        $collected = [];

        foreach ($rows as $collected['value']) {
        }

        foreach ($rows as $collected['key'] => $ignored) {
        }

        foreach ($rows as [$collected['first'], $collected['second']]) {
        }

        foreach ($rows as $index => [$collected['third']]) {
        }

        return $collected;
    }

    // The write target is the scope's last statement, so nothing terminates the
    // search for an enclosing construct before the method's own closing brace.
    // A scope brace is not a dynamic member name, so it must not be mistaken
    // for one -- doing so would read $collected as an offset and flag it.
    public function assignsAsTheLastStatementInAScope(array $rows, array $collected): void
    {
        foreach ($rows as $collected['value']) {
        }
    }

    // The nesting can invert: a closure puts whole statements inside an
    // accessor's offset, so a write target can sit *within* an index rather
    // than around one. The target is still a target -- the `foreach`
    // parentheses and the destructuring pattern each enclose it more tightly
    // than the index brackets do. Deciding by the innermost enclosing construct
    // keeps that straight; asking only "is an index bracket anywhere outside
    // me?" would answer yes and flag a write.
    public function assignsInsideAnAccessorOffset(array $rows, array $data, array $collected, array $source): array
    {
        $data[
            (function () use ($rows, $collected) {
                foreach ($rows as $collected['value']) {
                }

                return count($collected);
            })()
        ] = 'set';

        $data[
            (function () use ($source, $collected) {
                [$collected['first']] = $source;
                list($collected['second']) = $source;

                return count($collected);
            })()
        ] = 'set';

        return $data;
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
