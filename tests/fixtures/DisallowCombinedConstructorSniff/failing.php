<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Constructors.DisallowCombinedConstructor.
 *
 * One class per signal, so each of the three violation codes is provoked
 * independently and a class that stopped reporting could not be masked by its
 * siblings:
 *
 *   ModeFlag       a bool parameter selecting between initialization paths
 *   TypeSwitch     a parameter's runtime type selecting between them
 *   ArgumentCount  the constructor reading its own argument list
 */

final class Mailer
{
}

final class NullLogger
{
}

/**
 * Mode-flag branching: `$queued` is a boolean flag, and the `if` it heads picks
 * one of two initialization paths. Two constructors wearing one signature.
 */
final class ModeFlagged
{
    public function __construct(string $address, bool $queued)
    {
        if ($queued) {
            $this->transport = new NullLogger();
        } else {
            $this->transport = new Mailer();
        }

        $this->address = $address;
    }
}

/**
 * Parameter-type switching: `$source` arrives as either a string or an array,
 * and the constructor decides which constructor it is by asking.
 */
final class TypeSwitched
{
    public function __construct(string|array $source)
    {
        if (is_string($source)) {
            $this->lines = explode("\n", $source);
        } else {
            $this->lines = $source;
        }
    }
}

/**
 * Poor-man's overloading: the constructor reads the argument list the caller
 * actually supplied and initializes accordingly.
 */
final class Overloaded
{
    public function __construct()
    {
        $arguments = func_get_args();

        $this->transport = func_num_args() === 0 ? new NullLogger() : new Mailer();
    }
}
