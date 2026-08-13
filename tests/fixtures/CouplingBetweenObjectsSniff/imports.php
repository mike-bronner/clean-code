<?php

/**
 * Every shape a `use` statement takes, and what each one contributes.
 *
 * Seven classes are imported here — plain, aliased, two through a group, two
 * through a comma-separated statement, and one that is never used. The function
 * and constant imports contribute nothing: neither names a class.
 *
 * An import is file-wide, so all seven are charged to every class below. That
 * is what makes the three classes distinguishable by count alone: naming an
 * imported type again adds nothing, and naming an unimported short name adds
 * exactly one.
 */

declare(strict_types=1);

namespace Imp;

use Imp\Dep\Plain;
use Imp\Dep\Aliased as Alias;
use Imp\Dep\Grouped\{First, Second as Two};
use Imp\Dep\Multi1, Imp\Dep\Multi2;
use Imp\Dep\Unused;
use function Imp\Dep\helper;
use const Imp\Dep\SOME_CONSTANT;

/**
 * No body at all: the seven imports are the whole coupling.
 */
class ImportsAlone
{
}

/**
 * The same seven. Every type named here is already imported, and an import and
 * the short name it enables are one dependency, not two — so resolving the
 * short names is what keeps this class level with the one above.
 */
class ImportAndUsage
{
    private Plain $held;

    public function m(Alias $a, First $b): void
    {
        $made = new Two();
        Multi1::make();
        $value = Multi2::VALUE;
    }
}

/**
 * The seven imports plus one: a short name nobody imported resolves against the
 * current namespace instead, and `Imp\Unimported` is a type this file has not
 * already counted.
 */
class UnimportedShortName
{
    public function m(Unimported $a): void
    {
    }
}
