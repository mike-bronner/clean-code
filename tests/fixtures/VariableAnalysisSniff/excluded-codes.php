<?php

declare(strict_types=1);

namespace App;

/**
 * Every construct here trips one of the VariableAnalysis codes that rules.xml
 * excludes, and none of them is an undefined-variable read. The test proves
 * both halves: silent through the master ruleset, noisy through the
 * unconfigured standard.
 */
class ExcludedCodes
{
    // UnusedVariable — assigned, never read.
    public function unusedLocal(): string
    {
        $unused = 'never read';

        return 'result';
    }

    // VariableRedeclaration — a parameter redeclared as a global.
    public function redeclaredAsGlobal(string $shadowed): string
    {
        global $shadowed;

        return $shadowed;
    }
}

// SelfOutsideClass / StaticOutsideClass — a static reference with no class
// around it.
function selfOutsideClass(): void
{
    self::$missing = 1;
}

function staticOutsideClass(): void
{
    static::$missing = 1;
}
