<?php

declare(strict_types=1);

namespace App;

/**
 * Every construct here trips one of the three VariableAnalysis codes that
 * rules.xml excludes, and none of them is an undefined-variable read or an
 * unused local. The test proves both halves: silent through the master
 * ruleset, noisy through the unconfigured standard.
 *
 * UnusedVariable used to belong here. It is now kept, not excluded — it
 * carries PHPMD's UnusedLocalVariable (#118) — so its fixtures are
 * unused-locals.php and unused-locals-divergences.php instead.
 */
class ExcludedCodes
{
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
