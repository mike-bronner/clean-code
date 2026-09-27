# Routes: Types (API / View)

**API:** API routes should be within an `API` route namespace.

**View:** should have no namespace/prefix (as the API routes do). Controllers
should be responsible for a single model but for all views pertaining to that
model, e.g. `/resources/views/reports/index.blade.php`, `create.blade.php`,
`show.blade.php`.

**Takeaway:** the two route types are separated by namespace — API routes are
grouped under `API`, view routes stay unprefixed — and each view controller
owns exactly one model, serving every view that belongs to it.

## Compliant

```php
namespace App\Http\Controllers\API;

class InvoiceController
{
}
```

## Non-compliant

```php
namespace App\Http\Controllers;

class ApiInvoiceController
{
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Routes.ApiControllerNamespace` | no |

Code review checks the parts of this standard that the sniffs cannot see.
