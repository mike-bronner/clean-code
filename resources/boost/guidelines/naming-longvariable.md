# PHPMD Naming: LongVariable

- A property, parameter, or local variable name is at most 20 characters, not
  counting the `$`. Code at file scope is not checked.
- A name that long usually carries information that belongs in a type, a
  smaller scope, or a value object.

## Compliant

```php
public function remind(Invoices $invoices): int
{
    $overdueCount = $invoices->overdue()->count();

    return $overdueCount;
}
```

## Non-compliant

```php
public function remind(Invoices $invoices): int
{
    $numberOfOverdueInvoices = $invoices->overdue()->count();

    return $numberOfOverdueInvoices;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.LongVariable` | no |

Code review checks the parts of this standard that the sniffs cannot see.
