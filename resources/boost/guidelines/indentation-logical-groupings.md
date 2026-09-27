# Indentation: Logical Groupings

- If-statements with complex logic should have one condition per line, and
  groupings of conditions (using parentheses) should indent subsequent
  conditions to the first within the parentheses.

## Compliant

```php
if (
    $order->isPaid()
    && (
        $order->isShipped()
        || $order->isDigital()
    )
) {
    return;
}
```

## Non-compliant

```php
if (
    $order->isPaid()
    && ($order->isShipped()
    || $order->isDigital())
) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Indentation.LogicalGroupings` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
