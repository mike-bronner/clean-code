<?php

Http::get('https://example.com/orders');
Http::withToken('secret')->post('https://example.com/orders');
Http::FAKE;
Http::fake(...);
Https::fake();
ApiHttp::fake();
$http::fake();
$responses->fake();
$this->http->fake();
$this->fakeIt();

$this->createMock(PaymentGateway::class);
$this->mock(App\Support\Client::class);
$this->mock(\Client::class);
$this->mock(GuzzleHttp\ClientFactory::class);
$this->mock(Client::DEFAULT);
$this->mock(CLIENT);
$this->mock($class);
$this->mock(Client::class . $suffix);
$this->mock('GuzzleHttp\Client' . $suffix);
$this->mock();
$this->createMock(originalClassName: Client::class);
Mockery::close();
$builder->mock;
