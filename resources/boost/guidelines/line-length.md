# Line Length

- Lines of code should be no longer than **100 characters** and may absolutely
  be no longer than **120 characters**.

## Compliant

```php
$invoice = $this->invoices->createFor(
    customer: $customer,
    items: $items,
    dueAt: $dueAt,
);
```

## Non-compliant

```php
$invoice = $this->invoices->createFor(customer: $customer, items: $items, dueAt: $dueAt, notes: $notes, currency: $currency);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.Files.LineLength` | no |
