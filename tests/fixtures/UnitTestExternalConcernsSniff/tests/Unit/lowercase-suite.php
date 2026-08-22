<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest
{
    use RefreshDatabase;

    public function testItReachesPastItsSubject(): void
    {
        Http::fake();

        $this->getJson('/orders');
    }
}
