# Indentation: Multi-Line Statements

- If a single statement extends over multiple lines, any lines subsequent to
  the first should be indented by one level.

## Compliant

```php
$invoice = new Invoice(
    customer: $customer,
    total: $total,
);
```

## Non-compliant

```php
$invoice = new Invoice(
        customer: $customer,
        total: $total,
);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.WhiteSpace.MultiLineStatementIndent` | yes |

Standard: [docs/standards/indentation-multi-line-statements.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/indentation-multi-line-statements.md)
