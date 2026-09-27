# Clear Code: Group Code By Concepts

Multiple ideas together form a concept. Combine multiple statements into
groups separated by empty lines.

## Compliant

```php
$order = $this->findOrder($id);
$order->markPaid();

$receipt = $this->receiptFor($order);
$this->mailer->send($receipt);
```

## Non-compliant

```php
$order = $this->findOrder($id);
$order->markPaid();
$receipt = $this->receiptFor($order);
$this->mailer->send($receipt);
```

## Enforcement

Enforced by code review only. No sniff checks this standard.

Standard: [docs/standards/clear-code-group-code-by-concepts.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/clear-code-group-code-by-concepts.md)
