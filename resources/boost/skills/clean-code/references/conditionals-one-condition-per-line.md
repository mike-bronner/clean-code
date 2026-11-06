# Conditionals: One Condition Per Line

- If there is only a single condition in an if-statement, keep the condition
  portion on a single line; do not place the condition on its own line.
- For conditions consisting of multiple conditions, place each condition on
  its own line with the operator **preceding** the condition.
- The same applies to a boolean expression of multiple conditions in an
  assignment, a return statement, an arrow-function body or a match-arm body.
  A single-condition expression there may span lines.
- When such a condition feeds a ternary, the `?` and the `:` each start
  their own line after the conditions.
- The same applies inside a ternary branch. The `?` and the `:` start their
  own lines, and each further condition of a branch sits one level deeper
  than that branch's `?` or `:` line.

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

$isComplete = $isPaid
    && $isShipped;

$label = $isPaid
    && $isShipped
    ? 'done'
    : 'open';

$isReady = $isPaid
    ? $isShipped
        && $isTracked
    : $isPickup;
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

$isComplete = $isPaid && $isShipped;

$label = $isPaid
    && $isShipped ? 'done' : 'open';

$isReady = $isPaid ? $isShipped && $isTracked : $isPickup;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.OneConditionPerLine` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
