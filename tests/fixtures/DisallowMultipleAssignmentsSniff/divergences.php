<?php

declare(strict_types=1);

/**
 * Where the sniff and this standard's chained-assignment heuristic disagree,
 * in both directions. Nothing here is a defect; each shape is recorded so the
 * boundary is pinned rather than discovered later.
 *
 * Reported although the heuristic does not ask for it: an assignment written
 * inside an if/switch/case/match header. The sniff's rule is "an assignment
 * must be the first block of code on its line", which a condition assignment
 * breaks whether or not a second name is bound. Those lines are reported by
 * Generic.CodeAnalysis.AssignmentInCondition as well, so the two rules agree
 * and each says so in its own words.
 *
 * Silent although the heuristic does ask for it: a chain inside a while
 * header, and any chain built from compound operators.
 */
class SniffBoundaries
{
    public function evaluate(array $data): string
    {
        // Reported, FoundInControlStructure — inside the if's own parentheses.
        if ($single = count($data)) {
            $single++;
        }

        // Reported, FoundInControlStructure — a switch subject.
        switch ($subject = 1) {
            // Reported, Found rather than FoundInControlStructure: a case
            // expression carries no parentheses of its own, so the sniff finds
            // no owning control structure to name.
            case $label = 1:
                break;
        }

        // Reported, FoundInControlStructure — a match subject.
        $matched = match ($arm = 1) {
            default => 'z',
        };

        // Silent. The sniff returns on any assignment nested in a while
        // condition, so even a chain goes unreported here. The line is not
        // unwatched: Generic.CodeAnalysis.AssignmentInCondition reports it
        // under FoundInWhileCondition, at warning severity.
        while ($drained = $popped = array_pop($data)) {
            $single += $drained;
        }

        // Silent for the same reason, in the do...while spelling.
        do {
            $seed = 1;
        } while ($repeat = $seed);

        // Silent — the sniff registers T_EQUAL only, so a chain whose second
        // binding uses a compound operator is invisible to it.
        $total = $accumulated .= 'x';

        // Silent — the exemption for a "for" loop's own header.
        for ($cursor = 0; $found = $data[0] ?? null; $cursor++) {
            $single += $cursor;
        }

        return $single . $subject . $label . $matched . $popped . $repeat . $total . $accumulated . $found;
    }
}
