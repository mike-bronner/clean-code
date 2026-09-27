# Clear Code: Encapsulate Related Classes in a Domain

- Group related classes into a domain representing functional blocks in the
  real world.
- Domain-driven design takes this to the extreme by creating software
  abstractions called domain models that include business logic linking actual
  product conditions to code.

## Compliant

```php
namespace App\Billing;

class Invoice
{
}
```

## Non-compliant

```php
namespace App\Helpers;

class InvoiceHelper
{
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ClearCode.JunkDrawerNamespace` | no |

Code review checks the parts of this standard that the sniffs cannot see.
