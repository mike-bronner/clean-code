<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelBoundaryPass
{
    public function process(): void
    {
        foreach ($this->items as $item) {
            if ($item->isReady()) {
                $item->run();
            }
        }
    }
}
