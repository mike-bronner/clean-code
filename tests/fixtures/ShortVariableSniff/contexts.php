<?php

/**
 * The three contexts PHPMD allows a short name in, each with the same name
 * occurring earlier outside that context.
 *
 * The exemption is keyed to where the name first occurs, not to the name
 * itself: `$i`, `$e` and `$v` are exempt in passing.php because their first
 * occurrence is a `for` init, a `catch` binding and a `foreach` value. Here
 * each is written once beforehand as an ordinary local, and each is reported
 * — so the silence in passing.php cannot be a hardcoded allowance for those
 * spellings.
 *
 * phpmd 2.15 reports the same three lines, for the same reason: it marks a
 * name processed at its first occurrence, before consulting the context.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

class Contexts
{
    public function counted(): int
    {
        $i = 5;

        for ($i = 0; $i < 3; $i++) {
            echo $i;
        }

        return $i;
    }

    public function caught(): int
    {
        $e = 1;

        try {
            $e = 2;
        } catch (\Throwable $e) {
            return 0;
        }

        return $e;
    }

    public function looped(array $rows): int
    {
        $v = 0;

        foreach ($rows as $v) {
            echo $v;
        }

        return $v;
    }
}
