# Testing: Guidelines

- Start where you would start writing code; the first test doesn't have to be
  elegant or correct — just get started.
- Goal: get to "Shameless Green" quickly (ugly code that satisfies all tests).
- Code is written for understanding, not extreme pattern adherence; the human
  is the focus.
- Always write unit and integration tests, testing success and failure for
  each scenario.
- Only test public methods; cover protected/private methods through the public
  ones. Uncovered non-public methods are inaccessible (remove) or the tests
  aren't comprehensive.
- Tests document functionality through careful naming.
- Mock external interfaces you don't control (test success and failure), but
  also write integration tests so mocks don't go stale. Do not mock classes
  you control.

## Compliant

```php
it('totals an invoice', function (): void {
    $invoice = Invoice::factory()->make(['subtotal' => 100]);

    expect($invoice->total())->toBe(100);
});
```

## Non-compliant

```php
it('totals an invoice', function (): void {
    $calculator = Mockery::mock(App\Billing\TaxCalculator::class);
    $method = new ReflectionMethod(Invoice::class, 'computeTotal');

    expect($method->invoke(new Invoice($calculator)))->toBe(100);
});
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Testing.NoReflectionAccess` | no |
| `CleanCode.Testing.NoFirstPartyMocks` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/testing-guidelines.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/testing-guidelines.md)
