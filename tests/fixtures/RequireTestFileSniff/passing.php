<?php

declare(strict_types=1);

namespace Fixture;

/**
 * The contract floor's compliant fixture. Every declaration here is one the
 * sniff must stay silent on *without* a companion test existing, which is what
 * lets them share one file: the flat fixture names are fixed to this directory,
 * and no test tree is resolvable from it.
 *
 * The compliant concrete class — the one that is silent because its companion
 * was found — cannot live here for exactly that reason. It is src/Covered.php,
 * asserted in tests/Standards/RequireTestFileTest.php.
 */
interface Readable
{
    public function value(): int;
}

trait Countable
{
    public function count(): int
    {
        return 0;
    }
}

enum Level: int
{
    case Low = 1;
    case High = 2;
}

abstract class AbstractBase
{
    abstract public function value(): int;
}

readonly abstract class ReadonlyAbstractBase
{
    abstract public function value(): int;
}

function anonymous(): object
{
    return new class () {
        public function value(): int
        {
            return 0;
        }
    };
}
