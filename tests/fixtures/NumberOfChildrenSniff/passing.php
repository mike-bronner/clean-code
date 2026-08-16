<?php

declare(strict_types=1);

namespace Fixture\Passing;

/**
 * Fourteen direct children, one short of the default threshold of 15. The
 * threshold is inclusive, so this file is the silent half of the boundary pair
 * that failing.php completes.
 */
abstract class Base
{
}

class Child1 extends Base
{
}

class Child2 extends Base
{
}

class Child3 extends Base
{
}

class Child4 extends Base
{
}

class Child5 extends Base
{
}

class Child6 extends Base
{
}

class Child7 extends Base
{
}

class Child8 extends Base
{
}

class Child9 extends Base
{
}

class Child10 extends Base
{
}

class Child11 extends Base
{
}

class Child12 extends Base
{
}

class Child13 extends Base
{
}

class Child14 extends Base
{
}

/**
 * Implementing an interface is not extending it. Sixteen implementors report
 * nothing, and an interface is never a subject in the first place.
 */
interface Contract
{
}

class Implementor1 implements Contract
{
}

class Implementor2 implements Contract
{
}

class Implementor3 implements Contract
{
}

class Implementor4 implements Contract
{
}

class Implementor5 implements Contract
{
}

class Implementor6 implements Contract
{
}

class Implementor7 implements Contract
{
}

class Implementor8 implements Contract
{
}

class Implementor9 implements Contract
{
}

class Implementor10 implements Contract
{
}

class Implementor11 implements Contract
{
}

class Implementor12 implements Contract
{
}

class Implementor13 implements Contract
{
}

class Implementor14 implements Contract
{
}

class Implementor15 implements Contract
{
}

class Implementor16 implements Contract
{
}

/**
 * Children of a child count toward that child, never toward the class above
 * it. Deep is a grandchild of Base and leaves Base's own count alone.
 */
class Deep extends Child1
{
}

/**
 * An interface extending sixteen interfaces is still not a class hierarchy.
 */
interface Wide extends Contract
{
}

/**
 * Anonymous classes are not counted as children, in PHPMD or here. Sixteen of
 * them extending one base leave that base silent.
 */
class AnonymousBase
{
}

final class AnonymousFactory
{
    /**
     * @return array<int, object>
     */
    public function make(): array
    {
        $instances = [];

        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };
        $instances[] = new class extends AnonymousBase {
        };

        return $instances;
    }
}

/**
 * `AnonymousBase::class` names a class and declares none. Reading it as a
 * declaration would open a class body that never closes and swallow every
 * import below it.
 */
final class ConstantReader
{
    public function read(): string
    {
        return AnonymousBase::class;
    }
}
