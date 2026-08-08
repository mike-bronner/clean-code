<?php

/**
 * Compliant input for CleanCode.Naming.ShortMethodName.
 *
 * Beyond names that simply clear the default minimum of three, this carries
 * the near-miss shapes the sniff has to stay silent on: short names that are
 * not function declarations at all, unnamed declarations, and a two-letter
 * name that measures three bytes. Each is a way the sniff could go wrong
 * without any name in failing.php changing.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortMethodName;

// Short *type* names. The sniff registers T_FUNCTION only, so an interface,
// trait, enum or class whose own name is one or two characters is untouched.
interface I
{
    public function run(): void;
}

trait T
{
    public function log(): void
    {
    }
}

enum E: string
{
    case Ok = 'ok';

    public function label(): string
    {
        return $this->value;
    }
}

class A implements I
{
    // A one-character constant and property: short names on declarations the
    // sniff does not register.
    public const N = 1;

    public int $x = 0;

    // Magic methods carry no carve-out here because PHPMD has none. They pass
    // for the ordinary reason instead: the shortest of them, __get, is five
    // characters, so none is ever below the default minimum.
    //
    // All four names the parity claim covers are declared here, and all four
    // are asserted once the minimum is raised past them. A carve-out for any
    // single name then turns that test red. __set and __construct were absent
    // before, so a carve-out naming those two went unreported.
    public function __construct()
    {
    }

    public function __get(string $k): mixed
    {
        return null;
    }

    public function __set(string $k, mixed $v): void
    {
    }

    public function __call(string $k, array $args): mixed
    {
        return null;
    }

    // Exactly at the minimum. One character shorter is a violation; this is
    // the passing half of that boundary.
    public function abc(): void
    {
    }

    // Two letters, three bytes. PHPMD measures byte length via strlen(), and
    // so does this sniff, so both accept it.
    public function añ(): void
    {
    }

    public function run(): void
    {
    }

    // Return by reference: the ampersand sits between the keyword and the
    // name, and the name past it is long enough.
    public function &reference(): int
    {
        return $this->x;
    }

    public function callers(): void
    {
        // Calls, not declarations. `ab` and `do` are short, but the sniff
        // measures declarations, so a call site of either is silent.
        ab();
        $this->do();
        A::ab();

        // Unnamed declarations. PHPCS gives closures T_CLOSURE and arrow
        // functions T_FN, so neither reaches a T_FUNCTION listener — matching
        // PHPMD, which has no name to measure on them either.
        $closure = function (int $n): int {
            return $n;
        };
        $arrow = fn (int $n): int => $n;

        // A first-class callable: still a reference to a function, not a
        // declaration of one.
        $callable = strlen(...);

        // Short variables and parameters are a different PHPMD rule
        // (ShortVariable), not this one.
        $n = $closure(1) + $arrow(1) + strlen((string) $callable);
    }
}
