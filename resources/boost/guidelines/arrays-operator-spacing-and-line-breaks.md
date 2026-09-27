# Arrays: Operator Spacing & Line Breaks

- All operators are surrounded by **1 space**, with the exception of an
  operator at the beginning of a statement — such as the not operator (`!`),
  which has no preceding space:

  ```php
  if (! $test) {
      // ...
  }
  ```

- When an expression wraps, operators to the right of the assignment operator
  **start the new line** rather than trailing the previous one.
- Don't line-break **after** a comparison or assignment operator — the operator
  may not dangle at the end of a line.

## Compliant

```php
$total = $subtotal
    + $shipping;

if (! $isPaid) {
    return;
}
```

## Non-compliant

```php
$total = $subtotal  +
    $shipping;

if (!$isPaid) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Operators.BinaryOperatorSpacing` | yes |
| `Squiz.Strings.ConcatenationSpacing` | yes |
| `CleanCode.Operators.NotOperatorSpacing` | yes |
| `CleanCode.Operators.OperatorLineBreak` | yes |
