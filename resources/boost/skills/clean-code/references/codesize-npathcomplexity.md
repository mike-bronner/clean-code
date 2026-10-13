# PHPMD CodeSize: NPathComplexity

- A method or function has fewer than 200 acyclic execution paths. The sniff
  reports it at 200 or more.
- Paths multiply across statements in sequence: two independent `if`s give 4
  paths, and eight give 256. A method with a modest number of branches can still
  reach the limit.
- Break the method into smaller pieces, so that each holds a few of the
  independent decisions.

## Compliant

```php
public function label(Order $order): string
{
    return implode(' ', [
        $this->priorityLabel($order),
        $this->regionLabel($order),
        $this->statusLabel($order),
    ]);
}
```

## Non-compliant

```php
public function label(Order $order): string
{
    $parts = [];

    if ($order->express) { $parts[] = 'express'; }
    if ($order->fragile) { $parts[] = 'fragile'; }
    if ($order->insured) { $parts[] = 'insured'; }
    // ... five more independent ifs: 256 paths

    return implode(' ', $parts);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Metrics.NPathComplexity` | no |

Code review checks the parts of this standard that the sniffs cannot see.
