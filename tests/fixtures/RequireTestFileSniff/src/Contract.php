<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * An interface under the source root with no test. Never flagged: the
 * tokenizer spells it T_INTERFACE, which the sniff does not register.
 */
interface Contract
{
    public function value(): int;
}
