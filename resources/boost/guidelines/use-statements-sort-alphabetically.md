# Use Statements: Sort Alphabetically

- Use statements should be ordered alphabetically.

## Compliant

```php
use App\Models\Invoice;
use App\Models\User;
```

## Non-compliant

```php
use App\Models\User;
use App\Models\{Invoice, Payment};
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.Namespaces.AlphabeticallySortedUses` | yes |
| `SlevomatCodingStandard.Namespaces.DisallowGroupUse` | no |
| `SlevomatCodingStandard.Namespaces.MultipleUsesPerLine` | no |
