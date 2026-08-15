<?php

/**
 * A declaration PHP cannot parse: a method with no parameter list.
 *
 * PHPCS still tokenises the file, and still emits T_FUNCTION, but sets no
 * `parenthesis_opener` on it — so the sniff has no boundary telling it which
 * tokens the declaration owns. It refuses to guess and says nothing about
 * that declaration.
 *
 * The well-formed `ok()` above it is the control: its parameter is short, it
 * is reported, and it proves the sniff processes this file rather than
 * aborting on the malformed input further down.
 *
 * This file is deliberately not valid PHP. Fixtures are never loaded — a
 * PHPUnit <directory> only collects *Test.php — and `composer lint` excludes
 * */fixtures/*, so it cannot break either.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

class Malformed
{
    public function ok($op): int
    {
        return $op;
    }

    public function broken {
        $bv = 1;
    }
}
