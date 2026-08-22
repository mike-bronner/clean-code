<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\UnusedPrivateElements;

/**
 * One unused private property and one unused private method in a named class,
 * beside used siblings that must stay silent.
 */
class ClassWithDeadCode
{
    private string $unusedProperty = 'dead';

    private int $usedProperty = 1;

    public function run(): int
    {
        return $this->usedProperty;
    }

    private function unusedMethod(): void
    {
    }
}

/**
 * The method-versus-property collision. `$this->foo` reads the property and
 * says nothing about the method, so the property is used and the method is
 * dead. A shared usage map would report neither.
 */
class SharedNameOnlyPropertyRead
{
    private string $foo = 'read';

    public function run(): string
    {
        return $this->foo;
    }

    private function foo(): string
    {
        return 'never called';
    }
}

/**
 * The mirror case: only the method is called, so the same-named property is
 * dead.
 */
class SharedNameOnlyMethodCalled
{
    private string $bar = 'never read';

    public function run(): string
    {
        return $this->bar();
    }

    private function bar(): string
    {
        return 'called';
    }
}

/**
 * An enum's unreferenced private method is dead code the enum body proves.
 */
enum EnumWithDeadCode: string
{
    case Draft = 'draft';

    public function label(): string
    {
        return $this->value;
    }

    private function unusedEnumHelper(): string
    {
        return 'dead';
    }
}

/**
 * An anonymous class body proves its own private members dead, on the pass
 * register()'s T_ANON_CLASS entry gives it.
 */
class HostsDeadAnonymousClass
{
    public function make(): object
    {
        return new class () {
            private string $unusedInner = 'dead';

            public function read(): string
            {
                return 'nothing';
            }

            private function unusedInnerHelper(): string
            {
                return 'dead';
            }
        };
    }
}

/**
 * A member name shared across the anonymous-class boundary. PHP denies an
 * anonymous class any access to the enclosing class's private members, so the
 * mentions below belong to the nested body alone. The enclosing `$tag` and
 * `shared()` are never touched outside it and are dead; the nested pair is
 * used and stays silent.
 */
class HostsCollidingAnonymousClass
{
    private string $tag = 'never read out here';

    public function make(): object
    {
        return new class () {
            private string $tag = 'inner';

            public function read(): string
            {
                return $this->tag . $this->shared();
            }

            private function shared(): string
            {
                return 'inner';
            }
        };
    }

    private function shared(): string
    {
        return 'never called out here';
    }
}
