# Type Hints and Return Types

- Type hint all parameters, return values, and properties.
- Type hints serve as documentation, making code more fluent and readable.
- Type hints and return types prevent some logic errors from propagating,
  catching them as close as possible to their source.

## Compliant

```php
private int $subtotal;

public function add(int $amount): void
{
    $this->subtotal += $amount;
}
```

## Non-compliant

```php
private $subtotal;

public function add($amount)
{
    $this->subtotal += $amount;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.TypeHints.PropertyTypeHint` | yes |
| `CleanCode.TypeHints.ParameterTypeHint` | yes |
| `SlevomatCodingStandard.TypeHints.ReturnTypeHint` | yes |
