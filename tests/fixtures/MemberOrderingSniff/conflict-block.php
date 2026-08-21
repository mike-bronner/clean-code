<?php

/**
 * The trait-use shapes that make a naive name scan get the wrong answer. Every
 * `use` here is in alphabetical order under the sniff's actual comparison, so
 * the single reported violation is the multi-trait declaration and nothing
 * else — each shape is arranged to report an *extra* violation if the sniff
 * read it the other way. See tests/Standards/MemberOrderingTest.php for which
 * mutation each one catches.
 */

declare(strict_types=1);

class ConflictBlockModel extends Model
{
    use Alpha;
    use \Beta;
    use Bravo;
    use Delta, Epsilon, Zeta {
        Delta::render insteadof Epsilon, Zeta;
    }
    use Zulu\Charlie;
}
