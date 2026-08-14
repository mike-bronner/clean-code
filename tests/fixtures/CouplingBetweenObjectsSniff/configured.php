<?php

/**
 * Two small classes for exercising the `maximum` property: one naming three
 * types, one naming two.
 */

declare(strict_types=1);

namespace Conf;

class ThreeDependencies
{
    public function m(\Conf\Dep\A $a, \Conf\Dep\B $b, \Conf\Dep\C $c): void
    {
    }
}

class TwoDependencies
{
    public function m(\Conf\Dep\A $a, \Conf\Dep\B $b): void
    {
    }
}
