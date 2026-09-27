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
