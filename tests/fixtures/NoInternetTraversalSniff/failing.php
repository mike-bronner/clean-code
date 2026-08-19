<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Client as HttpClient;

$handle = curl_init('https://api.example.test/orders');
curl_exec($handle);
$socket = fsockopen('api.example.test', 443);
$stream = stream_socket_client('tls://api.example.test:443');
$rooted = \curl_init('https://api.example.test/ping');
$cased = CURL_EXEC($handle);
$body = file_get_contents('https://api.example.test/orders');
$plain = file_get_contents('http://api.example.test/orders');
$scheme = file_get_contents('HTTPS://api.example.test/orders');
$context = file_get_contents('https://api.example.test/orders', false);
$interpolated = file_get_contents("https://api.example.test/orders/{$id}");
$client = new Client();
$aliased = new HttpClient();
$qualified = new \GuzzleHttp\Client();
$lowercased = new client();
$labelled = file_get_contents(filename: 'https://api.example.test/orders');
$reordered = file_get_contents(offset: 0, filename: 'https://api.example.test/orders');
$nested = file_get_contents(offset: filesize(filename: 'local.json'), filename: 'https://api.example.test/orders');
