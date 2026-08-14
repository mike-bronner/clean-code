<?php

declare(strict_types=1);

namespace App\Sample;

use DateTimeImmutable;

/**
 * A compliant sample: every import, parameter, and private element is used,
 * and every comment explains rather than holds code.
 */
class Invoice
{
    private DateTimeImmutable $issuedAt;

    public function __construct(DateTimeImmutable $issuedAt)
    {
        $this->issuedAt = $issuedAt;
    }

    // Age is measured in whole days, rounding down.
    public function ageInDays(DateTimeImmutable $now): int
    {
        return $this->daysBetween($this->issuedAt, $now);
    }

    private function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->days;
    }
}
