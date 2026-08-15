<?php

/**
 * An unterminated class declaration. The file is already a parse error, so
 * PHP_CodeSniffer records no scope opener or closer for it and there is no body
 * to walk.
 */

declare(strict_types=1);

namespace Unclosed;

class NeverClosed
{
    public function m(\Unclosed\Dep\A $a): void
    {
    }
