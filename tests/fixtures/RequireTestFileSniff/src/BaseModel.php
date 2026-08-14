<?php

declare(strict_types=1);

namespace Fixture\Src;

/**
 * An abstract class under the source root with no test. Still T_CLASS, so this
 * is the one exemption the sniff has to read off the declaration rather than
 * get from the tokenizer: it is covered through its concrete subclasses.
 */
abstract class BaseModel
{
    abstract public function value(): int;
}
