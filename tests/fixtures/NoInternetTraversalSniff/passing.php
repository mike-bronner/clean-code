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

// Named arguments that state no URL for the filename: one labels a different
// parameter, the other names the filename but not where it points.
$otherParameter = file_get_contents(context: 'https://api.example.test/orders');
$namedDynamic = file_get_contents(filename: $url);

// Primitives named as first-class callables. Each builds a Closure and calls
// nothing, so no connection is opened and no URL is read.
$open = curl_init(...);
$send = curl_exec(...);
$connect = fsockopen(...);
$stream = stream_socket_client(...);
$read = file_get_contents(...);

// Heredoc and nowdoc arguments the file states no single URL for. The first
// reads a local path; the second and third are bodies of several physical
// lines, tokenized one token per line exactly as a split quoted literal is;
// the fourth has no body at all; the fifth is the head of an expression; the
// sixth labels a different parameter and names a local path for the filename.
$heredocLocal = file_get_contents(<<<PATH
    fixtures/orders.json
    PATH);
$heredocSplit = file_get_contents(<<<URL
    https://api.example.test/orders
    ?page=1
    URL);
$heredocLeading = file_get_contents(<<<URL

    https://api.example.test/orders
    URL);
$heredocEmpty = file_get_contents(<<<URL
    URL);
$heredocJoined = file_get_contents(<<<URL
    https://api.example.test/
    URL . $path);
$heredocOtherParameter = file_get_contents(context: <<<URL
    https://api.example.test/orders
    URL, filename: 'stub.json');
