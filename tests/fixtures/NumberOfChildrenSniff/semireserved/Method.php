<?php

/**
 * `class`, `trait`, `interface`, and `enum` are semi-reserved: PHP allows each
 * as a member name, and the tokenizer emits the declaration keyword's own token
 * for it. This file spells one as a method name.
 *
 * The declaration it announces never arrives — a body-less method ends in a
 * semicolon — so a parse that records the keyword as awaiting a body leaves that
 * entry behind. The next brace at the same parenthesis depth then claims it, and
 * the brace of the `namespace Fixture\SemiReserved\Consumer` block below is that
 * brace. Everything in the block is then read as being inside a class body,
 * where a `use` is a trait's rather than an import — so `Imported` is dropped
 * from the import map and `extends Imported` is counted against a class in the
 * consumer's own namespace, which nothing declares.
 *
 * Two children for the imported parent and one for the local one, so a report is
 * read by count rather than only by its existence. The local pair is unaffected
 * either way: it is the anchor that says the run happened at all.
 */

declare(strict_types=1);

namespace Fixture\SemiReserved\Method {
    interface HasTrait
    {
        public function trait(): void;
    }
}

namespace Fixture\SemiReserved\Consumer {
    use Fixture\SemiReserved\Target\Imported;

    class Local
    {
    }

    class LocalChild extends Local
    {
    }

    class FirstImported extends Imported
    {
    }

    class SecondImported extends Imported
    {
    }
}

namespace Fixture\SemiReserved\Target {
    class Imported
    {
    }
}
