<?php

declare(strict_types=1);

class ArrayAccessorsFailing
{
    public function readsElements(array $payload): array
    {
        $name = $payload['name'];
        $city = $payload['address']['city'];
        $first = $payload[0];

        return [$name, $city, $first];
    }

    public function readsProperties(object $order, object $customer): array
    {
        $reference = $order->reference;
        $city = $order->address->city;
        $email = $customer?->email;

        return [$reference, $city, $email];
    }

    public function readsInExpressions(array $payload, object $order): bool
    {
        if ($payload['enabled'] === true) {
            return true;
        }

        return $order->status === $payload['status'];
    }

    public function readsNestedShapes(array $payload): string
    {
        $name = $payload['items'][0]->name;

        return strtoupper($name);
    }

    public function readsThroughIndirection(object $order, string $field): array
    {
        $translated = $order->$field['locale'];
        $registered = self::$registry['key'];
        $dynamic = $order->{$field};

        return [$translated, $registered, $dynamic];
    }

    public function readsSittingBesideWrites(array $payload, array $target, int $mask): array
    {
        $keyed = [$payload['id'] => $payload['name']];
        $target[$payload['index']] = 'set';
        $flags = $mask & $payload['flags'];

        foreach ($payload['rows'] as $row) {
            $keyed[] = $row;
        }

        $label = strtoupper($payload['label']);

        return [$keyed, $target, $flags, $label];
    }

    public function readsComputedIndexesInsideWriteTargets(
        array $payload,
        array $rows,
        array $source,
        array $target,
        object $order
    ): array {
        foreach ($rows as $target[$payload['value']]) {
        }

        foreach ($rows as $target[$payload['key']] => $ignored) {
        }

        foreach ($rows as $order->{$payload['member']}) {
        }

        foreach ($rows as $target[strtolower($payload['computed'])]) {
        }

        [$target[$payload['first']]] = $source;
        list($target[$payload['second']]) = $source;
        ['x' => $target[$payload['third']]] = $source;
        [$target[strtolower($payload['fourth'])]] = $source;

        return [$target, $order];
    }

    /**
     * The same computed offsets, written with an expression that carries a `{}`
     * or a `;` of its own. `match` arms, closures, and anonymous classes are
     * expressions, so an offset may be built inside one -- and then neither the
     * brace ending it nor the statement separator inside it ends the enclosing
     * offset. A search that stopped at either would take the read for the
     * target and drop it.
     */
    public function readsComputedIndexesSpanningBracesAndStatements(
        array $payload,
        array $rows,
        array $source,
        array $target
    ): array {
        foreach ($rows as $target[match (true) { default => $payload['matched'] }]) {
        }

        foreach ($rows as $target[(function () use ($payload) { return $payload['returned']; })()]) {
        }

        foreach ($rows as $target[(static function () use ($payload) { return $payload['static']; })()]) {
        }

        [$target[match (true) { default => $payload['first'] }]] = $source;
        list($target[match (true) { default => $payload['second'] }]) = $source;
        [$target[strtolower(match (true) { default => $payload['third'] })]] = $source;
        list($target[(function () use ($payload) { return $payload['fourth']; })()]) = $source;

        [
            $target[
                (new class () {
                    public function pick(array $payload): string
                    {
                        return $payload['fifth'];
                    }
                })->pick($payload)
            ],
        ] = $source;

        return $target;
    }

    /**
     * A read stays reportable beside an existence check. `isset()` exempts what
     * sits inside its own parentheses, not the rest of the condition around
     * them: only $payload['present'] asks whether an element exists, while
     * $payload['live'] reads one and is the missing-element case the standard
     * is about.
     */
    public function readsBesideAnExistenceCheck(array $payload): bool
    {
        if (isset($payload['present']) && $payload['live'] === 'x') {
            return true;
        }

        return false;
    }

    /**
     * The chain root takes in the `$` sigils in front of the variable: PHP 7's
     * uniform variable syntax reads this as `($$name)['key']`, so the read is
     * reported at the sigil and names $$name -- what data_get() has to be
     * handed. Naming $name instead would advise searching the *name* of the
     * array rather than the array, which no fallback can rescue.
     */
    public function readsThroughAVariableVariable(string $name): string
    {
        return $$name['key'];
    }

    /**
     * A `foreach` header whose subject carries a closure with a `foreach` of
     * its own, so two `as` tokens sit in the one header's span. The header's
     * own is the second: the one at the header's own parenthesis depth. Only
     * that one splits subject from target, and every root in the header is
     * decided against it.
     *
     * $rows and $trailing sit either side of the *nested* `as` and are both
     * part of the outer header's subject, which the loop reads, so both
     * report. $inner is the nested header's own target and $outer is the outer
     * header's, so neither does. Matching the first `as` in the span compared
     * $trailing against the nested one and dropped it as a write target.
     */
    public function readsAcrossANestedForeachClause(
        array $rows,
        array $inner,
        array $outer,
        array $trailing
    ): void {
        foreach (mapRows($rows['pending'], function (array $row) use ($inner, $trailing): void {
            foreach ($row as $inner['each']) {
            }

            $ignored = $trailing['after'];
        }) as $outer['value']) {
        }
    }

    /**
     * The same shape with the nested `foreach` inside an anonymous class'
     * method rather than a closure. The header's own `as` is found by
     * parenthesis depth, so any scope that can hold a `foreach` statement is
     * skipped alike -- the lookup asks how deep a candidate sits, never what
     * kind of construct it sits in.
     *
     * $anonRows and $anonTrailing are the outer header's subject and report;
     * $anonInner is the nested header's target and $anonOuter the outer
     * header's, so neither does.
     */
    public function readsAcrossANestedForeachClauseInAnonymousClass(
        array $anonRows,
        array $anonInner,
        array $anonOuter,
        array $anonTrailing
    ): void {
        foreach (pickRows($anonRows['pending'], new class () {
            public function each(array $source, array $anonInner, array $anonTrailing): void
            {
                foreach ($source as $anonInner['each']) {
                }

                $ignored = $anonTrailing['after'];
            }
        }) as $anonOuter['value']) {
        }
    }

    /**
     * Two nested `foreach` headers at the same depth, in two closures passed
     * to the same call -- siblings rather than a staircase. The lookup passes
     * over every candidate that is not at the header's own depth, not merely
     * the first, so a second sibling `as` is skipped exactly as the first is.
     *
     * $firstTrailing and $secondTrailing follow their own sibling's `as` and
     * are both the outer header's subject, so both report. $firstInner and
     * $secondInner are the sibling headers' own targets and $siblingOuter is
     * the outer header's, so none of the three does.
     */
    public function readsAcrossSiblingNestedForeachClauses(
        array $firstInner,
        array $secondInner,
        array $siblingOuter,
        array $firstTrailing,
        array $secondTrailing
    ): void {
        foreach (mergeRows(
            function (array $row) use ($firstInner, $firstTrailing): void {
                foreach ($row as $firstInner['each']) {
                }

                $ignoredFirst = $firstTrailing['after'];
            },
            function (array $row) use ($secondInner, $secondTrailing): void {
                foreach ($row as $secondInner['each']) {
                }

                $ignoredSecond = $secondTrailing['after'];
            }
        ) as $siblingOuter['value']) {
        }
    }

    /**
     * The mirror of the cases above: the nested `foreach` sits in the header's
     * *target* clause, so its `as` comes *after* the header's own rather than
     * before it. Taking the last `as` in the span would answer this header
     * with the nested one and report $trapTarget -- the header's own write
     * target -- as a read.
     *
     * $trapTarget is that target and stays silent, and $trapInner is the
     * nested header's own target and stays silent too. The two reads report:
     * $trapRows is the header's subject, and $trapOffset is read to build the
     * target's offset, which every computed offset in a write target is.
     */
    public function readsInAForeachTargetHoldingANestedForeachClause(
        array $trapRows,
        array $trapTarget,
        array $trapInner,
        array $trapOffset
    ): void {
        foreach ($trapRows['pending'] as $trapTarget[(function () use ($trapInner, $trapOffset): string {
            foreach ($trapOffset['each'] as $trapInner['row']) {
            }

            return 'slot';
        })()]) {
        }
    }

    /**
     * The dynamic-member half of the same table: an offset built inside
     * `$order->{...}` with a brace or a statement of its own. These are last in
     * the class and followed by no further statement on purpose -- see
     * tokenizer-limits.php for the PHP_CodeSniffer scope-map defect this shape
     * triggers in whatever follows it.
     */
    public function readsComputedDynamicMembersSpanningBraces(
        array $payload,
        array $rows,
        object $order
    ): object {
        foreach ($rows as $order->{match (true) { default => $payload['member'] }}) {
        }

        foreach ($rows as $order->{(function () use ($payload) { return $payload['named']; })()}) {
        }

        return $order;
    }
}
