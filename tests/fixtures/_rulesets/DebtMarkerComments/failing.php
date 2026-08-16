<?php

// TODO: every marker, in every comment style, once each.
// FIXME: the slash line comment.
// HACK: the slash line comment.
// XXX: the slash line comment.

# TODO: the hash line comment.
# FIXME: the hash line comment.
# HACK: the hash line comment.
# XXX: the hash line comment.

/* TODO: the single-line block comment. */
/* FIXME: the single-line block comment. */
/* HACK: the single-line block comment. */
/* XXX: the single-line block comment. */

/*
 * TODO: the multi-line block comment.
 * FIXME: the multi-line block comment.
 * HACK: the multi-line block comment.
 * XXX: the multi-line block comment.
 */

/**
 * A ledger carrying one of each marker in its docblock.
 *
 * TODO: the docblock.
 * FIXME: the docblock.
 * HACK: the docblock.
 * XXX: the docblock.
 */
class Failing
{
    public function total(): int
    {
        return 1;
    }
}
