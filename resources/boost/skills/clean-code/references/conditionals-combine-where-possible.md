# Conditionals: Combine Where Possible

- Combine sequential conditions that have the same result.

## Compliant

```php
if (
    $order->isCancelled()
    || $order->isRefunded()
) {
    return;
}
```

## Non-compliant

```php
if ($order->isCancelled()) {
    return;
}

if ($order->isRefunded()) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.CombinableConditions` | no |

Code review checks the parts of this standard that the sniffs cannot see.
