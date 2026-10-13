# Operators: Evaluative

- Evaluative operators should never have a new line to the right or left —
  the operator sits on the same line as both of its operands.

```php
if ("hello" === "world") {
    //
}
```

- Comparison: `==`, `===`, `!=`, `<>`, `!==`, `<`, `>`, `<=`, `>=`, `<=>`
- Type: `instanceof`

## Compliant

```php
if ($status === 'paid') {
    return;
}
```

## Non-compliant

```php
if (
    $status
    === 'paid'
) {
    return;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Operators.DisallowNewlineAroundEvaluativeOperators` | yes |
