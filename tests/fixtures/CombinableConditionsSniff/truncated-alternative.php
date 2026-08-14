<?php

declare(strict_types=1);

/**
 * An alternative-syntax chain cut off before its `endif`, for
 * CleanCode.Conditionals.CombinableConditions. The tokenizer assigns no
 * scope_closer to a clause whose terminator never arrives.
 */

final class TruncatedAlternative
{
    public function chain(int $code): string
    {
        if ($code === 1):
            return 'same';
        elseif ($code === 2):
            return 'same';
