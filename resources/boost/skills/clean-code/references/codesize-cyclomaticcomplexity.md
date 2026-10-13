# PHPMD CodeSize: CyclomaticComplexity

- A method or function has a cyclomatic complexity below 10. The sniff reports
  it at 10 or more.
- Complexity is the number of decision points plus one. Each `if`, `elseif`,
  `for`, `foreach`, `while`, `case`, `catch`, `?:`, `&&`, `||`, `and` and `or`
  adds one.
- Break a complex method into smaller methods, one decision each.

## Compliant

```php
public function shippingCost(Order $order): int
{
    return $this->rates->for($order->destination)->cost($order->weight);
}
```

## Non-compliant

```php
public function shippingCost(Order $order): int
{
    if ($order->destination === 'US' && $order->weight < 5) {
        return 5;
    }

    if ($order->destination === 'US' || $order->destination === 'CA') {
        return $order->express ? 20 : 10;
    }

    // ... enough further branches to reach 10
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Metrics.CyclomaticComplexity` | no |

Code review checks the parts of this standard that the sniffs cannot see.
