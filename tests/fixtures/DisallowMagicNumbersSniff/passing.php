<?php

declare(strict_types=1);

const RETRY_LIMIT = 3;

#[Version(major: 2)]
final class Enrollment
{
    public const MAX_CLASSES_PER_STUDENT = 7;

    private int $enrolled = 12;

    public function __construct(
        private int $capacity = 30,
        private ?Money $fee = new Money(4500)
    ) {
    }

    #[Version(major: 4)]
    public function hasRoom(int $requested = 5): bool
    {
        return $this->enrolled + $requested <= self::MAX_CLASSES_PER_STUDENT;
    }

    public function nextIndex(): int
    {
        return $this->enrolled - 1;
    }

    public function isEmpty(): bool
    {
        return $this->enrolled === 0;
    }
}

enum Term: int
{
    case Fall = 202601;
    case Spring = 202602;
}

interface Capped
{
    public const CEILING = 999;
}

trait Weighted
{
    private float $weight = 1.5;
}

$anonymous = new class () {
    public int $slots = 250;
};

$identity = static fn (int $value = 60): int => $value * 1;

$closure = function (int $limit = 80): int {
    return $limit;
};

$tally = 0;
$offset = -1;
$ratio = 0.0;
$mask = 0x1;

declare(ticks=5);
