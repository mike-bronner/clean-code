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

        // The same two sections, with the assignment wrapped in a closure that
        // carries semicolons of its own. Those terminate the closure's
        // statements; they are not the header's section separators, so the
        // list() is still in the increment section and still ordinary.
        for ($cursor = 0; $cursor < 3; (function () use ($data): void {
            $side = 1;
            list($thirteenth, $fourteenth) = $data;
            unset($side, $thirteenth, $fourteenth);
        })(), $cursor++) {
            unset($cursor);
        }

        // The initialiser side of the same shape.
        for ((function () use ($data): void {
            $side = 1;
            list($fifteenth, $sixteenth) = $data;
            unset($side, $fifteenth, $sixteenth);
        })(), $index = 0; $index < 3; $index++) {
            unset($index);
        }

        // The same again with the closure assigned rather than invoked, in the
        // initialiser. Its body sits at the header's own parenthesis depth, so
        // only skipping the body as a scope keeps its semicolons out of the
        // separator list. Counting them instead would take the first two as
        // the header's separators and put this list() between them — reported
        // as a condition it is nowhere near.
        for ($handler = function () use ($data): void {
            $side = 1;
            list($seventeenth, $eighteenth) = $data;
            unset($side, $seventeenth, $eighteenth);
        }, $step = 0; $step < 3; $step++) {
            unset($step, $handler);
        }

        // The silent half of the for-header coverage: one shape here for each
        // construct PHPCS scopes in a header, each with the list() in a
        // section that is not the condition. The reported half lives in
        // failing.php. Every line below flags if the section test is widened
        // to accept any part of the header, so this is where an over-correcting
        // fix gets caught.
        //
        // An arrow function has no braced body to skip, but the tokenizer
        // still gives it a scope_closer — set to whatever token ends its
        // expression. In the initialiser that is the header's own first
        // separator. The list() beside it is still in the initialiser and
        // still ordinary.
        for ($shortHandler = fn (): int => 1, list($nineteenth, $twentieth) = $data; $tick < 3; $tick++) {
            unset($tick, $shortHandler, $nineteenth, $twentieth);
        }

        // The increment side of the same shape. There the arrow function's
        // scope_closer is the header's own closing parenthesis instead.
        for ($slot = 0; $slot < 3; $laterHandler = fn (): int => 2, list($twentyFirst, $twentySecond) = $data) {
            unset($slot, $laterHandler, $twentyFirst, $twentySecond);
        }

        // An anonymous class in the initialiser: a braced body, so its own
        // statement semicolons are skipped and the list() beside it stays in
        // the initialiser.
        for ($object = new class {
            public function value(): int
            {
                $inner = 1;

                return $inner;
            }
        }, list($twentyThird, $twentyFourth) = $data; $slice < 3; $slice++) {
            unset($slice, $object, $twentyThird, $twentyFourth);
        }

        // A match expression in the initialiser: braced too, and its arms hold
        // no semicolons at all, so nothing here can be mistaken for one.
        for ($choice = match (true) {
            default => 1,
        }, list($twentyFifth, $twentySixth) = $data; $round < 3; $round++) {
            unset($round, $choice, $twentyFifth, $twentySixth);
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
