# Testing: Development Process (TDD)

- Write unit tests before implementing classes (only implement classes, never
  procedural code).
- Always do Red/Green/Refactor TDD; write tests for the code you'd like in an
  optimal world, make the failing test pass with minimum code, expand,
  refactor, repeat until MVP.
- Two perspectives: when writing tests, keep the larger business domain in
  mind; when writing code to satisfy tests, only think about the test (do not
  think about business logic).
- As tests get more specific, code should become more generic; consider the
  Transformation Priority Premise.
- Never add code that won't be used; remove unused code.
- Use cyclomatic complexity as a guide for the number of tests (≈1 test per
  complexity unit).
- Wait to DRY out duplication until a few tests cover it, so the correct
  abstraction reveals itself.

## Compliant

```php
class Invoice
{
    public function total(): int
    {
        return $this->subtotal;
    }
}
```

## Non-compliant

```php
function invoiceTotal(array $invoice): int
{
    return $invoice['subtotal'];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Files.NoProceduralCode` | no |
| `CleanCode.Testing.RequireTestFile` | no |

Code review checks the parts of this standard that the sniffs cannot see.
