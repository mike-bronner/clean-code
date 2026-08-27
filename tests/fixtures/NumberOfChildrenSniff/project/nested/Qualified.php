<?php

declare(strict_types=1);

namespace Fixture\Other;

/**
 * Reaching the parent by its fully qualified name, with no import at all, and
 * one child of a trait-using class to keep a trait `use` in the fixture set.
 */
class Qualified1 extends \Fixture\Project\Base
{
}

class Qualified2 extends \Fixture\Project\Base
{
}

class Qualified3 extends \Fixture\Project\Base
{
}

trait Helper
{
}

class UsesTrait
{
    use Helper;
}
