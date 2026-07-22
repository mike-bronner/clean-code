<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelViolations
{
    public function threeLevels(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                while ($item > 0) {
                    $item--;
                }
            }
        }
    }

    public function fourLevels(): void
    {
        if ($this->flag) {
            for ($i = 0; $i < 10; $i++) {
                while ($i > 0) {
                    foreach ($this->items as $item) {
                        $i--;
                    }
                }
            }
        }
    }

    public function nestedClosureAndBody(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $fn = function () use ($item): void {
                    if ($item) {
                        echo $item;
                    }
                };
            }
        }
    }

    public function switchInsideLoop(): void
    {
        for ($i = 0; $i < 10; $i++) {
            switch ($this->value) {
                case 1:
                    if ($this->flag) {
                        $i = 0;
                    }
                    break;
            }
        }
    }

    public function tryInsideNestedLoops(): void
    {
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->items as $item) {
                try {
                    $item->run();
                } catch (\Throwable $e) {
                    if ($this->flag) {
                        echo 'x';
                    }
                }
            }
        }
    }

    public function matchInsideNestedLoops(): void
    {
        for ($i = 0; $i < 10; $i++) {
            foreach ($this->items as $item) {
                $result = match ($item) {
                    1 => 'one',
                    default => 'other',
                };
            }
        }
    }
}
