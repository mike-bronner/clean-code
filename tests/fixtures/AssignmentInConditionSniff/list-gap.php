<?php

declare(strict_types=1);

/**
 * The one shape PHPMD reports and Generic.CodeAnalysis.AssignmentInCondition
 * misses: a list() destructuring target, whose closing ")" ends the Generic
 * sniff's left-hand-side walk early. Verified against phpmd 2.15 with
 * rulesets/cleancode.xml — every line below is flagged there.
 *
 * The custom CleanCode.Conditionals.DisallowListAssignmentInCondition sniff
 * covers them, so through the master ruleset the pair reports each line once.
 */
class ListAssignmentConditions
{
    public function evaluate(array $data): string
    {
        if (list($first, $second) = $data) {
            return 'a';
        } elseif (list($third, list($fourth, $fifth)) = $data) {
            return 'b';
        }

        if (list('x' => $keyed) = $data) {
            return 'c';
        }

        return $first . $second . $third . $fourth . $fifth . $keyed;
    }
}
