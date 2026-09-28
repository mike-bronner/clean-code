# Clear Code: Encapsulate Each Concept in a Method

Refactor each concept into its own method. Through careful naming, this
results in readable, clean code.

In a test file, the three test phase markers `// 🧪 Arrange`, `// 🧪 Act` and
`// 🧪 Assert` are structure, not section labels. Write each one exactly, with
nothing after it on the line. Any other form, such as `// 🧪 Act & Assert` or
`// Arrange`, is a section label.

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
