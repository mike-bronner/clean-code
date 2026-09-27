# Naming: Casing Conventions

- All variables, properties, and methods should be in **camelCase**
- All SQL fields should be in **snake_case**
- All SQL keywords should be in **UPPERCASE**
- All class names should be in **PascalCase**
- All URLs, query strings, and config keys should be in **snake_case**
- All environment variables should be in **UPPER_SNAKE_CASE**

## Compliant

```php
class InvoiceMailer
{
    public function sendReminder(Invoice $pendingInvoice): void
    {
    }
}
```

## Non-compliant

```php
class invoice_mailer
{
    public function send_reminder(Invoice $pending_invoice): void
    {
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Squiz.NamingConventions.ValidVariableName` | no |
| `PSR1.Methods.CamelCapsMethodName` | no |
| `Squiz.Classes.ValidClassName` | no |

Standard: [docs/standards/naming-casing-conventions.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/naming-casing-conventions.md)
