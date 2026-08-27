<?php

Http::fake();
Http::fakeSequence();
Http::preventStrayRequests();
\Http::fake();
Illuminate\Support\Facades\Http::fake();
HTTP::fake();
Http::FAKE();
Http::fake(...$responses);

$this->createMock(Client::class);
$this->mock(\GuzzleHttp\Client::class);
$this?->mock(Client::class);
Mockery::mock(Illuminate\Http\Client\Factory::class);
Mockery::mock(Client::class, $expectations);
$this->mock('GuzzleHttp\Client');
$this->createMock("Illuminate\\Http\\Client\\PendingRequest");
