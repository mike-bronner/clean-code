# Pattern: SOLID

- **Single Responsibility**: a class should have one and only one reason to
  change (a Model defines relationships/scopes/attributes; a Controller handles
  request/response; an Action is a single-purpose invokable).
- **Open-Closed**: objects should be open for extension but closed for
  modification.
- **Liskov Substitution**: classes and their sub-classes should be substitutable
  without breaking code.
- **Interface Segregation**: clients shouldn't be forced to depend on methods
  they don't use; split interfaces when signatures don't apply to all
  implementers.
- **Dependency Inversion**: depend on abstractions, not concretions; specify the
  interface instead of the concrete class (Action classes are a good example).

## Compliant

```php
public function __construct(
    private PaymentGateway $gateway,
) {
}

public function charge(Invoice $invoice): void
{
    $this->gateway->charge($invoice);
}
```

## Non-compliant

```php
public function charge(Invoice $invoice): void
{
    match ($invoice->gateway) {
        'stripe' => (new StripeGateway())->charge($invoice),
        'paypal' => (new PaypalGateway())->charge($invoice),
    };
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.CodeSize.TooManyMethods` | no |
| `CleanCode.Classes.TooManyPublicMethods` | no |
| `CleanCode.Metrics.ExcessiveClassComplexity` | no |
| `CleanCode.Classes.ExcessiveClassLength` | no |
| `CleanCode.Metrics.ExcessivePublicCount` | no |
| `CleanCode.Metrics.TooManyFields` | no |
| `CleanCode.Metrics.CouplingBetweenObjects` | no |
| `CleanCode.Conditionals.TypeDiscriminatorDispatch` | no |
| `CleanCode.Pattern.ThrowOnlyMethodOverride` | no |
| `CleanCode.Pattern.TooManyInterfaceMethods` | no |
| `CleanCode.Classes.DisallowConstructorInstantiation` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/pattern-solid.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/pattern-solid.md)
