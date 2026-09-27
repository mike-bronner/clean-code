# PHPMD Design: CountInLoopExpression

- Do not call `count()` or `sizeof()` in a loop's condition.
- The condition runs on every iteration. A loop that changes the array while it
  re-counts it also moves its own end point. Take the size once, before the
  loop.

## Compliant

```php
$total = count($items);

for ($index = 0; $index < $total; $index++) {
    echo $items[$index];
}
```

## Non-compliant

```php
for ($index = 0; $index < count($items); $index++) {
    echo $items[$index];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ControlStructures.DisallowCountInLoopExpression` | no |

Code review checks the parts of this standard that the sniffs cannot see.
