<?php

/**
 * A comment sitting between a variable and its member-access operator.
 *
 * PHPMD works from an AST, where a comment is trivia: it cannot come between a
 * variable and the `->`, `?->`, or `::` that makes the variable part of a
 * member access, so the exemption applies either way. A token-based sniff has
 * to step over the comment itself.
 *
 * Every name here is 22 to 25 bytes without the leading `$`, so every one is
 * past the default maximum of 20. Each first occurs as the object of a member
 * access —
 * which, because the name is recorded before the exemption is applied, silences
 * it for the whole method even though a plain assignment follows. So this
 * fixture reports nothing, in this sniff and in a live PHPMD 2.15.0 run alike.
 *
 * Each method pairs a commented form with the same shape uncommented, so the
 * two cannot drift apart unnoticed.
 */

declare(strict_types=1);

namespace Tests\Fixtures\LongVariableSniff;

class CommentedMemberAccessHost
{
    public function blockCommentBeforeArrow(): void
    {
        $objectAccessedPastComment /* trivia */ ->refresh();
        $objectAccessedPastComment = 1;
    }

    public function noCommentBeforeArrow(): void
    {
        $objectAccessedNoComments->refresh();
        $objectAccessedNoComments = 1;
    }

    public function blockCommentBeforeColon(): void
    {
        $staticHolderPastComment /* trivia */ ::create();
        $staticHolderPastComment = 1;
    }

    public function blockCommentBeforeNullsafe(): void
    {
        $nullsafeHolderPastComm /* trivia */ ?->refresh();
        $nullsafeHolderPastComm = 1;
    }

    public function lineCommentBeforeArrow(): void
    {
        $objectPastLineComments // trivia
            ->refresh();
        $objectPastLineComments = 1;
    }

    public function commentBeforeStaticField(): void
    {
        self:: /* trivia */ $staticFieldPastComment;
        $staticFieldPastComment = 1;
    }
}
