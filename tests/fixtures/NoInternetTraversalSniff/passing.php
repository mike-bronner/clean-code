<?php

namespace Tests\Feature;

use Acme\Support\Client;
use Illuminate\Support\Facades\Http;

// The sanctioned route: a faked third-party API reached through the Http facade.
Http::fake(['api.example.test/*' => Http::response(['ok' => true])]);
$faked = Http::get('https://api.example.test/orders');

// Local reads. The function is ordinary; only a URL argument makes it a request.
$stub = file_get_contents(__DIR__ . '/stub.json');
$named = file_get_contents('stub.json');
$relative = file_get_contents('./fixtures/orders.json');

// Arguments the file does not state the value of.
$dynamic = file_get_contents($url);
$joined = file_get_contents('https://api.example.test/' . $path);
$leading = file_get_contents($base . 'https://api.example.test/');
$split = file_get_contents('https://api.example.test/orders
    ?page=1');

// Names that are not calls to PHP's own function.
$this->curl_init('https://api.example.test/orders');
Transport::curl_exec($handle);
$transport?->fsockopen('api.example.test', 443);
$property = $transport->stream_socket_client;
$variable = $curl_init;

// A class that is not the Guzzle client.
$support = new Client();
$other = new \Acme\Support\Client();
$dynamicClass = new $clientClass();
$anonymous = new class {
    public function send(): void
    {
    }
};

function curl_exec(string $handle): void
{
}

// A call with no argument at all — there is no URL to read, so there is
// nothing the file states about where it points.
$empty = file_get_contents();
