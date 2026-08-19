<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\{LazilyRefreshDatabase};
use Illuminate\Foundation\Testing\{WithFaker, DatabaseMigrations as Migrations};
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class ExternalConcernsTest
{
    use RefreshDatabase;

    use DatabaseMigrations, WithFaker {
        WithFaker::setUpFaker insteadof DatabaseMigrations;
    }

    public function testDatabaseTraitsInEverySpelling(): void
    {
        $this->assertTrue(true);
    }

    public function testFacadeFakes(): void
    {
        Bus::fake();
        Event::fake();
        Http::fake();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        Storage::fake();
        \Illuminate\Support\Facades\Http::fake();
    }

    public function testHttpKernelCalls(): void
    {
        $this->delete('/orders/1');
        $this->deleteJson('/orders/1');
        $this->get('/orders');
        $this->getJson('/orders');
        $this->patch('/orders/1');
        $this->patchJson('/orders/1');
        $this->post('/orders');
        $this->postJson('/orders');
        $this->put('/orders/1');
        $this->putJson('/orders/1');
        $this?->get('/orders');
    }
}
