<?php

// TODO: split this file once the invoicing module lands.
# TODO drop the compatibility shim

/* TODO - the totals are recomputed on every read */
/*
 * TODO
 */

/**
 * An invoice that still owes a rewrite.
 *
 * TODO
 * TODO: move the rounding into the money object.
 */
class Failing
{
    public function total(): int
    {
        return 1;
    }
}
