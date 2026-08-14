<?php

declare(strict_types=1);

/**
 * Conditions that compare rather than assign. Nothing here may be reported.
 */
class CompliantConditions
{
    public function evaluate(array $data, string $name): string
    {
        if ($name === 'bar') {
            return 'exact';
        } elseif ($name == 'baz') {
            return 'loose';
        }

        if (count($data) > 0 && $name !== '') {
            return 'both';
        }

        $index = 0;

        while ($index < count($data)) {
            $index++;
        }

        for ($cursor = 0; $cursor < 10; $cursor++) {
            $index += $cursor;
        }

        do {
            $index--;
        } while ($index > 0);

        switch (true) {
            case $name === 'a':
                return 'a';
            default:
                break;
        }

        return match ($name) {
            'x' => 'X',
            default => 'other',
        };
    }

    /**
     * An assignment is fine as a statement, and fine in a for-loop's
     * initialiser and increment sections — only the condition section is a
     * condition. A "=>" inside a condition is an array key, not an assignment.
     */
    public function assignOutsideConditions(array $data): int
    {
        $total = 0;

        for ($cursor = 0; $cursor < 3; $cursor += 1) {
            $total = $total + $cursor;
        }

        if (in_array($total, ['low' => 1, 'high' => 2], true)) {
            $total = 0;
        }

        return $total;
    }
}
