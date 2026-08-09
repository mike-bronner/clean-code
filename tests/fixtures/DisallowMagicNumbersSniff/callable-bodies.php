<?php

declare(strict_types=1);

$arrow = static fn (int $value = 60): int => $value * 24;

$closure = function (int $limit = 80): int {
    return $limit + 36;
};

$object = new class () {
    public function scale(int $factor = 90): int
    {
        return $factor * 48;
    }
};
