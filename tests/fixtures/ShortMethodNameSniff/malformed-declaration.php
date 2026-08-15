<?php

/**
 * A declaration PHP cannot parse: a method with no parameter list.
 *
 * PHPCS still tokenises the file, and still emits T_FUNCTION, but sets no
 * `parenthesis_opener` on it — so the sniff has no boundary proving the next
 * token it finds is the name rather than something from further down the
 * file. It refuses to guess and says nothing about that declaration.
 *
 * The well-formed `ok()` above it is the control: it is short, it is
 * reported, and it proves the sniff processes this file rather than aborting
 * on the malformed input further down.
 *
 * This file is deliberately not valid PHP. Fixtures are never loaded — a
 * PHPUnit <directory> only collects *Test.php — and `composer lint` excludes
 * */fixtures/*, so it cannot break either.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortMethodName;

class Malformed
{
    public function ok(): void
    {
    }

    public function ab {}
}
