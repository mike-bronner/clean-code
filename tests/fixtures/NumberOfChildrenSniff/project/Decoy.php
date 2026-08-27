<?php

declare(strict_types=1);

namespace Fixture\Decoy;

/**
 * A second class of the same short name. Its own children must stay its own —
 * folding the two together is what comparing short names instead of fully
 * qualified ones would do.
 */
abstract class Base
{
}

class Decoy1 extends Base
{
}

class Decoy2 extends Base
{
}

class Decoy3 extends Base
{
}
