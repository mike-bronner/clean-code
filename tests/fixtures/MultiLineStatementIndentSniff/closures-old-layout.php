<?php

declare(strict_types=1);

$rewritten = preg_replace_callback(
        $pattern,
        function (array $match) use ($quote): string {
            $span = preg_replace_callback(
            $quote,
            function (array $attr): string {
                return implode(
                '=',
                $attr,
                );
            },
            $match[0]
            );

            return $span;
        },
        $content
    );

$mapped = array_map(function ($item) {
    $value = transform(
        $item,
    );

    return $value;
}, $items);

$handler = function () {
    return transform(
    $item,
    );
};

$writer = new class () {
    public function write(): void
    {
        $this->output(
            $line,
        );
    }
};

$label = match ($code) {
    1 => 'one',
    default => 'none',
};
