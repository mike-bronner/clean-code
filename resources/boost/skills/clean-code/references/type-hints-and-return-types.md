# Type Hints and Return Types

- Type hint all parameters, return values, and properties.
- **Exemption:** a property, static or instance, does not report when its
  nearest ancestor declaration is not private and has no native type. PHP
  refuses to load a child that adds a type there. This covers Eloquent's
  `$fillable`, `$casts` and `$table`, and Nova's `$title`, `$search` and
  `$group`. No `@var` docblock is needed. A private or typed nearest
  declaration, an undeclared property, and an ancestor the project's autoloader
  cannot load still report.
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
