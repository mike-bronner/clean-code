# Conditionals: One Condition Per Line

- If there is only a single condition in an if-statement, keep the condition
  portion on a single line; do not place the condition on its own line.
- For conditions consisting of multiple conditions, place each condition on
  its own line with the operator **preceding** the condition.

## Compliant

```php
if ($isPaid) {
    return;
}

if (
    $isPaid
    && $isShipped
) {
    return;
}
```

## Non-compliant

```php
if (
    $isPaid
) {
    return;
}

if ($isPaid && $isShipped) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.OneConditionPerLine` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
