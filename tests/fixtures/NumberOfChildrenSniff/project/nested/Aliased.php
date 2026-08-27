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

/**
 * The one consumer of the group-brace import above. Its parent is
 * Fixture\Project\Same1 — the alias's resolved target — and not a
 * Fixture\Project\Nested\Ignored, which is what the alias would give if the
 * group's prefix were dropped, nor Fixture\Project\Base, which is what folding
 * the group into the plain import on line 7 would give.
 *
 * Same1 is declared in Base.php, so this child is read back there.
 */
class GroupAliased extends Ignored
{
}
