<?php

declare(strict_types=1);

namespace Fixture\Project;

/**
 * The subject. Fifteen direct children reach it from four files and four ways
 * of spelling its name, which is the whole point of the fixture: a single-file
 * read of this file sees five of them.
 */
abstract class Base
{
}

class Same1 extends Base
{
}

class Same2 extends Base
{
}

class Same3 extends Base
{
}

class Same4 extends Base
{
}

class Same5 extends Base
{
}
