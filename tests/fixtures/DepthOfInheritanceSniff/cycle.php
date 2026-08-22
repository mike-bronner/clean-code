<?php

/**
 * A cycle, which PHP itself refuses to load and PHPMD reports nothing about.
 *
 * The walk has to notice it rather than count round the loop forever. Both
 * classes are deep enough to report if the count were allowed to run away.
 */

declare(strict_types=1);

namespace Cycle;

class Ouroboros extends Tail
{
}

class Tail extends Ouroboros
{
}
