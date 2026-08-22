<?php

namespace App\Tests\Unit;

use App\Models\User;
use App\Services\Payments as Pay;
use App\Models\{Order, Invoice as Bill};
use App\Jobs\Dispatch;
use function Vendor\Sdk\dispatch;
use App\Enums\Mode;
use const Vendor\Sdk\MODE;

$mock = $this->createMock(User::class);
$partial = $this->createPartialMock(\App\Models\User::class, ['save']);
$builder = $this->getMockBuilder('App\Models\User');
$mockery = Mockery::mock(Order::class);
$spy = Mockery::spy(Pay::class);
$laravel = $this->mock(Bill::class);
$relative = $this->partialMock(namespace\Support\Clock::class);
$laravelSpy = $this->spy(User::class);
$nullsafe = $this->container?->mock(User::class);
$uppercaseRoot = $this->createMock(\APP\Models\User::class);
$bare = $this->getMockBuilder(User);
$escaped = Mockery::mock("App\\Models\\User");
$withClosure = $this->mock(User::class, static fn () => null);
$uppercaseCreator = $this->CREATEMOCK(User::class);
$currentNamespace = $this->createMock(Support\Clock::class);
$functionImportIgnored = $this->createMock(Dispatch::class);
$constImportIgnored = Mockery::mock(Mode::class);

class UserServiceTest
{
    public function testItSaves(): void
    {
        $repository = $this->createMock(User::class);
    }
}
