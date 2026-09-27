# Clear Code: Encapsulate Each Concept in a Method

Refactor each concept into its own method. Through careful naming, this
results in readable, clean code.

## Compliant

```php
public function checkout(): void
{
    $this->reserveStock();
    $this->chargeCustomer();
}
```

## Non-compliant

```php
public function checkout(): void
{
    // Reserve stock
    $this->stock->reserve($this->items);

    // Charge customer
    $this->gateway->charge($this->total);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ClearCode.SectionComment` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/clear-code-encapsulate-each-concept-in-a-method.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/clear-code-encapsulate-each-concept-in-a-method.md)
