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

Standard: [docs/standards/conditionals-combine-where-possible.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/conditionals-combine-where-possible.md)
