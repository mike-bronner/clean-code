# Methods: Type Hints

Methods should have type-hinted parameters as well as a return type.

## Compliant

```php
public function total(Order $order): int
{
    return $order->subtotal + $order->shipping;
}
```

## Non-compliant

```php
public function total($order)
{
    return $order->subtotal + $order->shipping;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.TypeHints.ParameterTypeHint` | yes |
| `SlevomatCodingStandard.TypeHints.ReturnTypeHint` | yes |
| `CleanCode.TypeHints.InferredReturnType` | yes |

Standard: [docs/standards/methods-type-hints.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/methods-type-hints.md)
