<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures;

class MethodNestingLevelCompliant
{
    public function straightLine(): void
    {
        $a = 1;
        $b = 2;
    }

    public function oneLevel(): void
    {
        if ($this->flag) {
            $a = 1;
        }
    }

    public function twoLevels(): void
    {
        if ($this->flag) {
            foreach ($this->items as $item) {
                $a = $item;
            }
        }
    }

    public function ifElseChainStaysLevelOne(): void
    {
        if ($this->flag) {
            $a = 1;
        } elseif ($this->other) {
            $a = 2;
        } else {
            $a = 3;
        }
    }

    public function switchCaseCountsAsOneLevel(): void
    {
        switch ($this->value) {
            case 1:
                if ($this->flag) {
                    $a = 1;
                }
                break;
            default:
                $a = 0;
        }
    }

    public function tryCatchFinallyAtLevelOne(): void
    {
        try {
            $a = 1;
        } catch (\Throwable $e) {
            if ($this->flag) {
                $a = 2;
            }
        } finally {
            $a = 3;
        }
    }

    public function closureBodyIsLevelTwo(): void
    {
        $fn = function (): void {
            if ($this->flag) {
                $a = 1;
            }
        };
    }

    public function matchAtLevelOne(): void
    {
        $result = match ($this->value) {
            1 => 'one',
            default => 'other',
        };
    }
}
