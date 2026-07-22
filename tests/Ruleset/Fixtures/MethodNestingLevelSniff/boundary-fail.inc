<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelBoundaryFail
{
    public function process(): void
    {
        foreach ($this->items as $item) {
            if ($item->isReady()) {
                while ($item->hasNext()) {
                    $item->run();
                }
            }
        }
    }
}
