# Use Statements: No Unused Entries

- Use statements should not include unused entries.

## Compliant

```php
use App\Models\Invoice;

$invoice = new Invoice();
```

## Non-compliant

```php
use App\Models\Invoice;
use App\Models\User;

$invoice = new Invoice();
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.Namespaces.UnusedUses` | yes |

Standard: [docs/standards/use-statements-no-unused-entries.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/use-statements-no-unused-entries.md)
