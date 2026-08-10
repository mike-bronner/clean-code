<?php

namespace App\Tests\Unit;

use App\Models\User;
use Vendor\Sdk\Client;

// Outside every class-like scope the keywords name no class at all.
$orphan = $this->createMock(self::class);

// `self` in a trait names whichever class uses the trait, which this file
// never writes down.
trait Doubles
{
    public function double(): object
    {
        return $this->createMock(self::class);
    }
}

class AnonymousHostTest
{
    public function testItDoubles(): void
    {
        // `self` names the anonymous class it sits in, not the named class
        // further out. Reaching past it would resolve to AnonymousHostTest.
        $anonymous = new class {
            public function inner(): object
            {
                return $this->createMock(self::class);
            }
        };
    }
}

class VendorChildTest extends Client
{
    public function testItInherits(): void
    {
        $vendorParent = $this->createMock(parent::class);
    }
}

// A file caught mid-edit can leave an `extends` with no name after it, which
// names no parent either.
class BrokenParentTest extends
{
    public function testItHasNoParentName(): void
    {
        $malformed = $this->createMock(parent::class);
    }
}

// A class with no `extends` clause has no parent to name. The search for one
// is bounded by this class's own brace, so it cannot borrow the first-party
// parent of the class declared next.
class NoParentTest
{
    public function testItHasNoParent(): void
    {
        $none = Mockery::mock(parent::class);
    }
}

class UserServiceTest extends User
{
    public function testItSaves(): void
    {
        $itself = $this->createMock(self::class);
        $late = $this->createPartialMock(static::class, ['save']);
        $inherited = Mockery::mock(parent::class);
        $upper = $this->createMock(SELF::class);
    }

    public function testNearMisses(): void
    {
        // A keyword with no `::class` after it names no class.
        $constant = $this->createMock(self::DRIVER);
        $closure = $this->mock(static function (): void {
        });

        // The class reference has to be the whole argument.
        $concatenated = $this->createMock(self::class . 'Proxy');

        // A bare keyword names no class either. The written-name path accepts
        // a bare `User` because a name is a name whatever follows it; a
        // keyword only names a class through the `::class` constant.
        $bare = $this->createMock(self);
    }
}
