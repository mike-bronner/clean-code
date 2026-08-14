<?php

declare(strict_types=1);

namespace App\Fixtures;

// A named class the tokenizer never closed, so it carries no scope_closer and
// PHPCS builds no scope for it. There is nothing to measure, so the sniff stays
// silent even at a maximum of 0 — as PHPMD does, PHP being unable to compile
// the file at all.

class NeverClosed
{
    public function m(bool $a, bool $b): void
    {
        if ($a && $b) {
            echo 1;
        }
    }
