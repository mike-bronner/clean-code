# Classes: Class Naming

A class name must not repeat the folder it is filed under. `App\Services\
BillingService` says "service" twice: the namespace already files the type
under `Services`, so the suffix adds nothing at the declaration and lengthens
every reference to it. The name is `App\Services\Billing`.

Where a call site genuinely reads better with the fuller name, the import
carries it:

```php
use App\Services\Billing as BillingService;
```

That is where a suffix belongs — in the alias it disambiguates, not in the
declaration it duplicates.

**Takeaway:** name the class for what it *is*, not for the folder it is in;
alias at the call site when the folder's word adds clarity there.

## Compliant

```php
namespace App\Services;

class Billing
{
}
```

## Non-compliant

```php
namespace App\Services;

class BillingService
{
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.RedundantNamespaceSuffix` | no |

Code review checks the parts of this standard that the sniffs cannot see.
