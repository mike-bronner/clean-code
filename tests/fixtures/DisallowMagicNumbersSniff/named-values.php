<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->seconds = 1.5;
    $this->minutes = 2 * 60;
    $this->retries += 3;
    $this->limits['daily'] = 25;
    $this->settings->timeout = 30;
});

it('waits', function (): void {
    $count = 2;
    $delay = -5;
    $total = $count * 4;
    $cap = 9 + $count;
    $count ??= 7;
    $batch = [8];
    $result = compute(6);
});
