# PHPMD CleanCode: MissingImport

- Import every class, interface, trait, and enum with a `use` statement. Do not
  write a class name fully qualified inline.
- The `use` block is the file's dependency list, and an inline name hides a
  dependency from it.
- A fully qualified global function or constant, such as `\strlen()` or
  `\PHP_EOL`, is allowed.

## Compliant

```php
use App\Billing\Invoice;

public function make(): Invoice
{
    return new Invoice;
}
```

## Non-compliant

```php
public function make(): \App\Billing\Invoice
{
    return new \App\Billing\Invoice;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
