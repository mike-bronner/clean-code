<?php

/**
 * Continues the chain through a plain `use` import — the parent is written as
 * a bare short name that only the import resolves.
 */

declare(strict_types=1);

namespace Project\Mid;

use Project\Base\Sub;

class Mid1 extends Sub
{
}

class Mid2 extends Mid1
{
}
