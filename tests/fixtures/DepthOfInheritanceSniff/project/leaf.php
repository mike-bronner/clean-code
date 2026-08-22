<?php

/**
 * Three more links, each written a different way: through a group import with
 * an alias, through a `namespace\`-relative name, and through a fully
 * qualified one. Only Leaf3 reaches six parents.
 */

declare(strict_types=1);

namespace Project\Leaf;

use Project\Mid\{Mid2 as Aliased};

class Leaf1 extends Aliased
{
}

class Leaf2 extends namespace\Leaf1
{
}

class Leaf3 extends \Project\Leaf\Leaf2
{
}
