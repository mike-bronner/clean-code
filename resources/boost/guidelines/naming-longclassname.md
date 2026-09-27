# PHPMD Naming: LongClassName

- A class, interface, trait, or enum name is at most 40 characters.
- A name that long usually describes several responsibilities. Split the type
  instead of growing the name.

## Compliant

```php
final class InvoiceReminder
{
}
```

## Non-compliant

```php
final class OverdueInvoiceReminderEmailSchedulerAndLogger
{
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.LongClassName` | no |

Code review checks the parts of this standard that the sniffs cannot see.
