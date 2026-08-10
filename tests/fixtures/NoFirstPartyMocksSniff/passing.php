<?php

namespace App\Tests\Unit;

use App\Models\User;
use Psr\Log\LoggerInterface;
use Vendor\Sdk\PaymentGateway;
use Vendor\Sdk\{Client, Server as Host};

// Compliant: only interfaces the project does not control are mocked.
$gateway = $this->createMock(PaymentGateway::class);
$logger = $this->createMock(LoggerInterface::class);
$qualified = Mockery::mock(\Vendor\Sdk\PaymentGateway::class);
$literal = $this->getMockBuilder('Vendor\Sdk\PaymentGateway');
$global = $this->createMock(\DateTimeInterface::class);
$grouped = $this->spy(Host::class);
$groupedPlain = $this->mock(Client::class);

// A root that merely starts with the configured one, with no segment boundary
// after it, is a different namespace. A plain string prefix would swallow both.
$lookalike = $this->createMock(\Application\Order::class);
$sibling = $this->createMock(\Apples::class);

// A class reference the file's own tokens cannot resolve is never guessed at.
$dynamic = Mockery::mock($className);
$chained = $this->createMock(self::userClass());
$concatenated = $this->createMock(User::class . 'Proxy');
$concatenatedLiteral = $this->createMock('App\Models\User' . $suffix);
$constant = $this->mock(Registry::USER);
$empty = $this->getMockBuilder();
$interpolated = Mockery::mock("App\\Models\\{$name}");

// Calls that create no mock. `mockery` and `call` are not configured creators,
// a property read of a creator name calls nothing, and a dynamic member name is
// unknowable at token level.
$result = $this->call(User::class);
$named = $this->mockery(User::class);
$property = $builder->mock;
$dynamicName = $this->{$creator}(User::class);

// A class *declaring* a member that shares a creator name must not flag itself:
// a declaration carries no ->, ?-> or :: before the name.
class Doubles
{
    public function mock(string $class): object
    {
        return new $class();
    }
}
