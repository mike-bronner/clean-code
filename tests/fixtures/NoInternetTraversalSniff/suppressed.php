<?php

namespace Tests\Feature;

use GuzzleHttp\Client;

// phpcs:ignore CleanCode.Testing.NoInternetTraversal.Found -- deliberate smoke check
$handle = curl_init('https://api.example.test/orders');

// phpcs:disable CleanCode.Testing.NoInternetTraversal.Found -- deliberate smoke check
$body = file_get_contents('https://api.example.test/orders');
$client = new Client();
// phpcs:enable CleanCode.Testing.NoInternetTraversal.Found

$reported = curl_exec($handle);
