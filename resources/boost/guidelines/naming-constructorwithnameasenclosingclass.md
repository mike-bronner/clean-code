# PHPMD Naming: ConstructorWithNameAsEnclosingClass

- Declare a constructor as `__construct()`. Never name a method after its own
  class.
- PHP 8 removed the PHP 4 constructor style. A method named after its class is
  an ordinary method that reads like a constructor.

## Compliant

```php
final class Invoice
{
    public function __construct(private int $total)
    {
    }
}
```

## Non-compliant

```php
class Invoice
{
    public function Invoice(int $total)
    {
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.NamingConventions.ConstructorName` | no |

Code review checks the parts of this standard that the sniffs cannot see.
