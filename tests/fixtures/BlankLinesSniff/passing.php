<?php

// Positive: single blank lines separate members and logical groups; no run of
// two, none directly after an opening brace, none directly before a closing
// one. The open-tag edge case has its own fixtures
// (after-open-tag-two-blanks.php / after-open-tag-three-blanks.php).

class CompliantExample
{
    private int $count = 0;

    private string $label = 'example';

    public function increment(): int
    {
        $this->count++;

        return $this->count;
    }

    public function emptyBody(): void
    {
    }

    public function singleStatement(): int
    {
        return 1;
    }
}

interface CompliantContract
{
    public function handle(): void;
}

trait CompliantHelper
{
    public function help(): callable
    {
        return function (): int {
            $value = 1;

            return $value;
        };
    }
}

enum CompliantStatus: string
{
    case Active = 'active';

    case Paused = 'paused';
}

function compliantProcedure(array $items): int
{
    $total = 0;

    foreach ($items as $item) {
        $total += $item;
    }

    return $total;
}
