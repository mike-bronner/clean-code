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

        // The same section, with an arrow function beside it. The tokenizer
        // gives the arrow function a scope_closer even though it has no braced
        // body: here the header's own first separator. Skipping to it would
        // swallow that separator and silence this condition entirely.
        for ($shortHandler = fn (): int => 1; list($twentieth, $twentyFirst) = $rows; $step++) {
            unset($step, $shortHandler, $twentieth, $twentyFirst);
        }

        // The increment side of the same shape, where the arrow function's
        // scope_closer is the header's own closing parenthesis instead. This
        // line cannot be made to fail by any change to the separator scan —
        // both separators are already collected before the scan reaches the
        // increment — so it is construct coverage, not a discriminating case.
        for ($slot = 0; list($twentySecond, $twentyThird) = $rows; $laterHandler = fn (): int => 2) {
            unset($slot, $laterHandler, $twentySecond, $twentyThird);
        }

        // An anonymous class beside the condition: braced, so it is skipped
        // whole and the condition still reads. Unlike the two lines above,
        // this one dies on its own if the skip is written per-construct and
        // leaves anonymous classes out.
        for ($object = new class {
            public function value(): int
            {
                $inner = 1;

                return $inner;
            }
        }; list($twentyFourth, $twentyFifth) = $rows; $slice++) {
            unset($slice, $object, $twentyFourth, $twentyFifth);
        }

        // A match expression beside the condition, likewise braced — though
        // its arms are expressions and hold no semicolons, so skipping it or
        // not cannot change the separator list. Recorded, not discriminating.
        for ($choice = match (true) {
            default => 1,
        }; list($twentySixth, $twentySeventh) = $rows; $round++) {
            unset($round, $choice, $twentySixth, $twentySeventh);
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
