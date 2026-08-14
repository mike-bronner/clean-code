<?php

declare(strict_types=1);

/**
 * Shapes the Generic sniff reports that PHPMD's IfStatementAssignment does
 * not. Verified against phpmd 2.15 with rulesets/cleancode.xml: every line
 * below is silent there and flagged here.
 *
 * PHPMD misses them because its rule reads only if/elseif clauses, only
 * accepts a plain "=", and only visits function and method bodies. They are
 * the same code smell, so the wider coverage is kept rather than narrowed.
 */
class BroaderThanPhpmd
{
    public function evaluate(array $data): string
    {
        $result = '';

        if ($result .= 'x') {
            return 'a';
        }

        if ($appended += 1) {
            return 'b';
        }

        if ($coalesced ??= 1) {
            return 'c';
        }

        while ($popped = array_pop($data)) {
            $result .= $popped;
        }

        for ($cursor = 0; $current = $data[0]; $cursor++) {
            $result .= $current;
        }

        do {
            $seed = 1;
        } while ($repeat = $seed);

        switch ($subject = 1) {
            case $label = 1:
                break;
        }

        $matched = match ($arm = 1) {
            default => 'z',
        };

        return $result . $appended . $coalesced . $repeat . $subject . $label . $matched . $arm;
    }
}

if ($fileScope = 'level') {
    (new BroaderThanPhpmd())->evaluate([]);
}
