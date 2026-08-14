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

    // The one staggered step no structure can answer once for every read: a
    // `foreach` header whose first `as` sits *inside* the construct the walk
    // steps out of, because a second `foreach` is nested in the header.
    //
    // $rows is before that `as` and $outer is after it, and both walk out
    // through constructs enclosed by the same header -- so the step out of
    // those constructs decides one way for one root and the other way for the
    // other. $rows reports; $outer is the header's own target and does not.
    // A compressed step that answered this one statically would drop $rows.
    public function decidesStaggeredRootsAgainstANestedForeachClause(
        array $rows,
        array $inner,
        array $outer
    ): void {
        foreach (mapRows($rows['pending'], function (array $row) use ($inner, $trailing): void {
            foreach ($row as $inner['each']) {
            }

            // $trailing is the far side of that `as`, reached through the same
            // constructs $rows is reached through. The hop-by-hop walk answers
            // it as the outer header's target and drops it -- the nested `as`
            // is the first one the header's search finds, and the walk compares
            // against it. Pinned as the walk's own answer, unchanged by the
            // step being compressed, not as an endorsement of it: it is the
            // same T_AS search either way, and #292 is about how many steps the
            // walk takes, not about which `as` it finds.
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
