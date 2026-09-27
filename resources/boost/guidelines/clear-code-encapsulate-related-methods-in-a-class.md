# Clear Code: Encapsulate Related Methods in a Class

- In Laravel, most business logic is contained within Models.
- Sometimes classes are needed to encapsulate concepts outside of models or
  other Laravel-prescribed functional classes.
- Action classes are optimal candidates for this purpose.

## Compliant

```php
class SendInvoice
{
    public function __invoke(Invoice $invoice): void
    {
        $this->mailer->send($invoice);
    }
}
```

## Non-compliant

```php
class SendInvoice
{
    public function __invoke(Invoice $invoice): void
    {
        $this->mailer->send($invoice);
    }

    public function resend(Invoice $invoice): void
    {
        $this->mailer->send($invoice);
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ClearCode.ActionSingleEntryPoint` | no |

Code review checks the parts of this standard that the sniffs cannot see.
