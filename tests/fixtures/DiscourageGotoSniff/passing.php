<?php

declare(strict_types=1);

namespace App;

/**
 * The compliant form of every shape failing.php spells with goto — a loop, an
 * early return, and an extracted method — plus the shapes where the *word*
 * "goto" appears without being the language construct: an identifier, a string,
 * a comment, and an array key.
 */
class Router
{
    private int $gotoCount = 0;

    public function retry(int $attempts): int
    {
        $attempt = 0;

        while ($attempt < $attempts) {
            $attempt++;
        }

        return $attempt;
    }

    public function resolve(int $param): int
    {
        if ($param === 42) {
            return $this->finish();
        }

        return $param;
    }

    private function finish(): int
    {
        return 42;
    }

    /**
     * A docblock may describe the construct: goto retry; retry:
     */
    public function describe(): string
    {
        // An inline comment may spell it out too: goto retry; retry:
        $single = 'goto retry;';
        $double = "goto retry; retry:";
        $heredoc = <<<SQL
            goto retry; retry:
            SQL;
        $keys = ['goto' => 1, 'retry:' => 2];

        return $single . $double . $heredoc . $keys['goto'] . $this->gotoCount;
    }

    public function gotoHelper(): int
    {
        return $this->gotoCount;
    }
}
