# Indentation: Methods (max 2 nesting levels)

- Methods should have no more than **2 levels of nesting**.

## Compliant

```php
foreach ($orders as $order) {
    $this->shipIfPaid($order);
}
```

## Non-compliant

```php
foreach ($orders as $order) {
    if ($order->isPaid()) {
        foreach ($order->items as $item) {
            $this->ship($item);
        }
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Metrics.MethodNestingLevel` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/indentation-methods-max-nesting-levels.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/indentation-methods-max-nesting-levels.md)
