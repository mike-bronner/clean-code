<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__ . '/../routes/web.php')
    ->withExceptions(function ($exceptions): void {
    })
    ->create();
\Illuminate\Foundation\Application::configure(basePath: $path)->create();
Illuminate\Foundation\Application::configure(basePath: $path)->create();
Http::withToken($token)->delete($url);
$send = function (PendingRequest $client) use ($url) {
    return $client->delete($url);
};
$send = fn (PendingRequest $client) => $client->delete($url);
$send = static fn (PendingRequest $client): mixed => $client?->withToken($token)->delete($url);
$send = fn (\Illuminate\Http\Client\PendingRequest $client) => $client->delete($url);
$send = fn (Client $client) => $client->delete($url);
Client::make()->delete($url);

class Gateway
{
    public function remove(PendingRequest $client, string $url): mixed
    {
        $client->withToken($this->token);

        return $client->delete($url);
    }

    public function removeEither(PendingRequest|Application $client): void
    {
        $client->delete($this->url);
    }

    public function removeBoth(PendingRequest&\Countable $client): void
    {
        $client->delete($this->url);
    }

    public function removeUnstated(PendingRequest&Countable $client): void
    {
        $client->delete($this->url);
    }
}
