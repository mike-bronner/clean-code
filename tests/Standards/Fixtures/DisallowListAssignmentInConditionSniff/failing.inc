<?php

declare(strict_types=1);

/**
 * A list() destructuring assignment in each condition-bearing construct the
 * sniff covers. Every line is flagged; the if and elseif lines are the ones
 * PHPMD's IfStatementAssignment rule reports too.
 */
class FailingListAssignments
{
    public function evaluate(array $data, array $rows): string
    {
        if (list($first, $second) = $data) {
            return 'if';
        } elseif (list($third, list($fourth, $fifth)) = $data) {
            return 'elseif';
        }

        if (list('x' => $keyed) = $data) {
            return 'keyed';
        }

        // Nested inside a call argument in the condition, which PHPMD reports
        // too because its own search descends the whole condition expression.
        if (count(list($sixth, $seventh) = $data) > 0) {
            return 'nested';
        }

        while (list($eighth, $ninth) = $rows) {
            unset($eighth, $ninth);
        }

        for ($cursor = 0; list($tenth, $eleventh) = $rows; $cursor++) {
            unset($tenth, $eleventh);
        }

        // The condition section of a for, reached through a closure that
        // carries semicolons of its own. Skipping the closure whole is what
        // keeps the header's own two separators in view, so this stays inside
        // the condition and is reported.
        for ($marker = 0; (function () use ($rows): bool {
            $flag = true;
            list($eighteenth, $nineteenth) = $rows;

            return $flag && $eighteenth === $nineteenth;
        })(); $marker++) {
            unset($marker);
        }

        do {
            $seed = $rows;
        } while (list($twelfth, $thirteenth) = $seed);

        switch (list($fourteenth, $fifteenth) = $data) {
            default:
                break;
        }

        return match (list($sixteenth, $seventeenth) = $data) {
            default => 'match',
        };
    }
}

if (list($fileScopeFirst, $fileScopeSecond) = [1, 2]) {
    unset($fileScopeFirst, $fileScopeSecond);
}
