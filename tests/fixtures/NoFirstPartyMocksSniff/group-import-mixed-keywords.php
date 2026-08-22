<?php

namespace App\Tests\Unit;

// A group import may mix class, function and const clauses, and the keyword
// binds only the clause it is written on. Prefixed with the group's namespace
// before the keyword is read, it sits mid-string where an anchored test cannot
// see it, and the function and the constant are imported as classes under the
// alias each is given.
use Vendor\Sdk\{Helper, function build as Baz, const MODE as Mood};

// The other spelling writes the keyword on the statement, where it binds every
// clause rather than only the one it stands in front of.
use function Vendor\Sdk\dispatch, Vendor\Sdk\resolve;
use const Vendor\Sdk\{DRIVER, LEVEL};

// The class clause of the mixed group still imports, so this is a vendor mock
// and stays silent under App.
$helper = $this->createMock(Helper::class);

// Neither alias imports a class, so both names resolve inside the declared
// namespace. Read as class imports they bind to Vendor\Sdk\… instead and fall
// silent here — and report under a Vendor root that must leave them alone.
$aliasedFunction = $this->createMock(Baz::class);
$aliasedConst = $this->createMock(Mood::class);

// The statement keyword binds the second clause as well as the first, so all
// four of these resolve inside the declared namespace too.
$firstFunction = $this->createMock(dispatch::class);
$secondFunction = $this->createMock(resolve::class);
$firstConst = $this->createMock(DRIVER::class);
$secondConst = $this->createMock(LEVEL::class);
