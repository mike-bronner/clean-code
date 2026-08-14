<?php

declare(strict_types=1);

namespace App\Fixtures;

// The one shape where this sniff and PHPMD disagree, and the disagreement is
// PHP_CodeSniffer's tokenizer rather than the rule: a closure declared inside a
// curly-brace property fetch in a `foreach` header leaves the enclosing class's
// scope_closer pointing at the method's closing brace instead of the class's
// own, and truncates the method's scope the same way. Measured live against
// PHPMD 2.15.0, the sniff reports 9 lines where PHPMD reports 10, and 2
// executable lines where PHPMD reports 5. Nothing the sniff can do about that
// without re-pairing braces itself, so the gap is pinned here rather than
// worked around.

class ClosureInPropertyFetch
{
    public function walk(array $rows, object $order, array $payload): object
    {
        foreach ($rows as $order->{(function () use ($payload) { return $payload['named']; })()}) {
        }

        return $order;
    }
}
