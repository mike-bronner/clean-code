<?php

/**
 * Nesting fixture for CleanCode.Functions.AvoidDuplicateFunctionBodies.
 *
 * The sniff compares outermost declarations only, and this file pins both
 * halves of that rule:
 *
 *   - Ledger holds two methods whose own bodies differ, each returning an
 *     anonymous class whose run() body is identical to the other's. Nothing is
 *     reported: the deliberate blind spot the rule buys.
 *   - Registry holds two methods whose bodies are identical, inner anonymous
 *     class and all. Exactly one warning is reported, on the outer method —
 *     never a second one for the inner pair, which would restate the same
 *     duplication at another nesting level.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\AvoidDuplicateFunctionBodiesSniff\Nesting;

class Ledger
{
    public function debitHandler(): object
    {
        $mode = 'debit';
        unset($mode);

        return new class {
            public function run(array $rows): array
            {
                $rows = array_values($rows);
                $rows = array_unique($rows);

                return $rows;
            }
        };
    }

    public function creditHandler(): object
    {
        return new class {
            public function run(array $rows): array
            {
                $rows = array_values($rows);
                $rows = array_unique($rows);

                return $rows;
            }
        };
    }
}

class Registry
{
    public function firstFactory(): object
    {
        return new class {
            public function build(array $rows): array
            {
                $rows = array_filter($rows);
                $rows = array_values($rows);

                return $rows;
            }
        };
    }

    public function secondFactory(): object
    {
        return new class {
            public function build(array $rows): array
            {
                $rows = array_filter($rows);
                $rows = array_values($rows);

                return $rows;
            }
        };
    }
}
