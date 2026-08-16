<?php

declare(strict_types=1);

namespace Fixture\Project\Nested;

use Fixture\Project\Base as Root;
use Fixture\Project\{Same1 as Ignored};
use function Fixture\Project\helper;
use const Fixture\Project\FLAG;

class Aliased1 extends Root
{
}

class Aliased2 extends Root
{
}

class Aliased3 extends Root
{
}
