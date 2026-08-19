<?php

declare(strict_types=1);

/**
 * Shapes Squiz.PHP.DisallowMultipleAssignments stays silent on: one assignment
 * per statement, plus every near miss that separates "two ideas bound in one
 * statement" from a construct that only looks like one.
 *
 * The near misses are the point. Each one is a place the sniff returns early,
 * and each is silent for a different reason, so a regression that removed any
 * single carve-out would redden this file rather than pass unnoticed.
 */
class OneIdeaPerStatement
{
    /**
     * A property default. Not a statement at all — the sniff bows out on any
     * assignment whose innermost scope is a class-like, so this is silent even
     * though "=" sits well inside the line.
     */
    public int $configured = 1;

    public static string $label = 'plain';

    /**
     * Parameter defaults, of which the sniff makes the same exemption: a
     * declaration binds one name per parameter, and the "=" is part of the
     * signature rather than a second idea in a statement.
     */
    public function evaluate(array $data, int $limit = 10, ?string $mode = null): string
    {
        $first = 'a';
        $second = 'b';
        $first .= $second;

        // A compound-operator chain. The sniff registers T_EQUAL only, so it
        // never sees this shape — a real limitation of the sniff rather than a
        // carve-out, recorded here so the silence is deliberate and pinned.
        $total = $count += 1;

        // A "for" loop's own initialiser. Assigning in a loop header is the
        // idiomatic spelling and the sniff exempts it explicitly.
        for ($cursor = 0; $cursor < $limit; $cursor++) {
            $first .= $cursor;
        }

        // A while condition, which the sniff returns on before it inspects
        // anything else. Generic.CodeAnalysis.AssignmentInCondition reports
        // this shape at warning severity instead; see
        // tests/Ruleset/IfStatementAssignmentTest.php.
        while ($row = array_pop($data)) {
            $first .= $row;
        }

        // A braceless control-structure body. Despite the name of the sniff's
        // FoundInControlStructure code, a single assignment following a
        // braceless header is NOT reported: the code is chosen by whether the
        // assignment sits inside the header's own parentheses, and this one
        // sits after the closing parenthesis.
        if ($limit > 0) $braceless = 1;

        // A condition carrying no assignment at all.
        if ($first === $second) {
            $braceless = 2;
        }

        // An array element and a property target, each one idea, each with the
        // assignment first on its line.
        $data['key'] = $first;
        $this->configured = $limit;

        // A closure and an arrow function, both with defaulted parameters.
        $closure = static function (int $size = 3): int {
            return $size;
        };
        $arrow = static fn (int $size = 4): int => $size;

        return $first . $second . $total . $count . $braceless . $closure(1) . $arrow(1) . $mode;
    }
}
