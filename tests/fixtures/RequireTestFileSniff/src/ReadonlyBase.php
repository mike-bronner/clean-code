<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * The same exemption with the modifiers written the other way round. PHP
 * accepts `readonly abstract` as readily as `abstract readonly`, so reading
 * only the token immediately before `class` would flag this one.
 */
readonly abstract class ReadonlyBase
{
    abstract public function value(): int;
}
