<?php

declare(strict_types=1);

/**
 * list() destructuring that is not a condition, plus conditions that hold no
 * list() destructuring. Nothing here may be reported.
 */
class PassingListAssignments
{
    public function evaluate(array $data, array $rows): string
    {
        // A plain destructuring statement: no enclosing parentheses at all.
        list($first, $second) = $data;

        // Inside a function call's parentheses, which carry no owner.
        var_dump(list($third, $fourth) = $data);

        // Inside a foreach header, which is an iteration target, not a
        // condition — the enclosing parentheses have an owner, but not one of
        // the condition-bearing kinds.
        foreach ([list($fifth, $sixth) = $data] as $pair) {
            unset($pair);
        }

        // A for-loop's initialiser and increment sections are not its
        // condition, so an assignment in either is ordinary.
        for (list($seventh, $eighth) = $data; $seventh < 3; list($ninth, $tenth) = $data) {
            unset($ninth, $tenth);
        }

        // A list() that is read rather than assigned to: the token after its
        // closing parenthesis is not "=", even though it sits inside an if
        // condition (by way of an immediately-invoked closure).
        if ((function () use ($rows): string {
            foreach ($rows as list($eleventh, $twelfth)) {
                return $eleventh . $twelfth;
            }

            return '';
        })()) {
            return 'read';
        }

        // Conditions that assign something other than a list() are the
        // Generic sniff's business, not this sniff's.
        if ($plain = 'bar') {
            return 'plain';
        }

        if ([$shortFirst, $shortSecond] = $data) {
            return $shortFirst . $shortSecond;
        }

        return $first . $second . $third . $fourth . $fifth . $sixth . $eighth . $tenth;
    }
}
