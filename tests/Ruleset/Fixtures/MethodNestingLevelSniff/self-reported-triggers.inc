<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelSelfReportedTriggers
{
    // A `for` whose body is only plain statements is itself the deepest
    // construct: nested in if -> foreach it sits at level 3 and is reported at
    // its own line (line 16). Deleting T_FOR from register() drops this report.
    public function forAtLevelThree(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                for ($j = 0; $j < 10; $j++) {
                    $j += $i;
                }
            }
        }
    }

    // A `switch` with only a plain `case`/`break` body is the deepest construct
    // at level 3, reported at its own line (line 30). Deleting T_SWITCH from
    // register() drops this report.
    public function switchAtLevelThree(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                switch ($i) {
                    case 1:
                        break;
                }
            }
        }
    }

    // A `do { } while ()` with a plain body is the deepest construct at level 3,
    // reported at the `do` on its own line (line 45), not the trailing `while`.
    // Deleting T_DO from register() drops this report.
    public function doAtLevelThree(): void
    {
        if ($this->a) {
            foreach ($this->items as $i) {
                do {
                    $i--;
                } while ($i > 0);
            }
        }
    }
}
