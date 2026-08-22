<?php

declare(strict_types=1);

// The staggered/staircase shape (#292): each read sits one construct deeper
// than the read before it, so no two of them share an enclosing construct
// beyond the one they are all inside. Every construct between a read and the
// verdict that settles it decides nothing, which is what makes the walk
// outward long -- and what a structure that skips those constructs in one hop
// has to get right without changing a single verdict.
//
// The reads are ordinary; what this fixture pins is that the answer does not
// change when the walk is compressed. Each method is one construct kind, so a
// compression scoped to the kind #292 illustrated (nested call arguments)
// cannot pass this whole file.
class ArrayAccessorsStaggeredNesting
{
    // Nested call arguments -- the shape #292 measured. Every enclosing
    // parenthesis belongs to an ordinary call, so all three reads are decided
    // by the same thing: nothing enclosing them decides anything.
    public function readsAtStaggeredCallDepths(array $first, array $second, array $third): string
    {
        return sprintf(
            '%s%s',
            $first['key'],
            sprintf('%s%s', $second['key'], sprintf('%s', $third['key']))
        );
    }

    // The same staircase built from short-array literals instead of calls. An
    // unassigned literal decides nothing either, so these reads report too.
    public function readsAtStaggeredArrayLiteralDepths(array $first, array $second, array $third): array
    {
        return [$first['key'], [$second['key'], [$third['key']]]];
    }

    // Three chain roots at three different depths whose nearest enclosing
    // `foreach` parentheses are the same closer, and which must not all be
    // decided alike:
    //
    // - $subject sits before `as`, one construct deeper than $target (inside
    //   the call). The `foreach` clause reads its subject, so the closer is
    //   transparent for it and the read reports.
    // - $target sits after `as`, directly inside the parentheses. The same
    //   closer is a write target for it and the read is dropped.
    // - $offset sits after `as`, one construct deeper than $target (inside
    //   the index). The index `]` decides first -- an offset, a read -- so
    //   the `foreach` closer is never reached.
    //
    // A compression that cached "this closer is transparent" from $subject's
    // walk and reused it for $target would flag a write; one that cached the
    // target verdict would drop a read.
    public function decidesStaggeredRootsAgainstOneForeachCloser(
        array $subject,
        array $target,
        array $offset
    ): void {
        foreach (array_values($subject['rows']) as $target[$offset['idx']]) {
        }
    }

    // The same three-way split one level deeper on both sides, so the
    // distinction is not an artefact of either root sitting directly inside
    // the parentheses.
    public function decidesDeeperStaggeredRootsAgainstOneForeachCloser(
        array $subject,
        array $target,
        array $offset
    ): void {
        foreach (array_values(array_filter($subject['rows'])) as $target[strtolower($offset['idx'])]) {
        }
    }

    // A `foreach` header holding a second `foreach`, nested inside the outer
    // header's own subject. Two `as` tokens then sit in the outer header's
    // span, and the outer header's own is the second of them -- the one at
    // the header's own parenthesis depth, not the first one in the span.
    //
    // $rows is before that `as` and $outer is after it, and both walk out
    // through constructs enclosed by the same header. $rows is part of the
    // header's subject and reports; $outer is the header's own target and
    // does not. A step that answered this one statically would drop $rows.
    public function decidesStaggeredRootsAgainstANestedForeachClause(
        array $rows,
        array $inner,
        array $outer
    ): void {
        foreach (mapRows($rows['pending'], function (array $row) use ($inner, $trailing): void {
            foreach ($row as $inner['each']) {
            }

            // $trailing sits past the *nested* header's `as` and before the
            // outer header's own, so it belongs to the outer header's subject
            // and reports: the nested `as` names the nested header's target
            // and decides nothing for a root outside that header. Comparing
            // $trailing against the first `as` in the span instead took it for
            // the outer header's write target and dropped it -- the false
            // negative #314 fixes. Matching the `as` at the header's own
            // parenthesis depth is what keeps this read reportable.
            $ignored = $trailing['after'];
        }) as $outer['value']) {
        }
    }

    // An existence check exempts what sits inside its parentheses however deep
    // that is: the check need not be the parentheses nearest the read. Deciding
    // it from the innermost pair alone would report this one.
    public function checksExistenceAtAStaggeredDepth(array $payload): bool
    {
        return array_key_exists('city', array_filter($payload['address']));
    }

    // An assigned destructuring pattern reached through a staircase. The
    // pattern's `]` is the write target for $target and $second however deep
    // inside it they sit; the offsets computing their slots are reads, and
    // the deeper of the two is separated from its deciding `]` by a call.
    public function destructuresAtStaggeredDepths(
        array $source,
        array $target,
        array $second,
        array $offset,
        array $slot
    ): void {
        [$target[strlen($offset['idx'])], [$second[$slot['idx']]]] = $source;
    }
}
