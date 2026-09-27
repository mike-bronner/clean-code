# Classes: Contracts (Interfaces)

- Contracts aim to loosen coupling of objects: coupling shifts from concrete
  implementations to abstract contracts — no logic, only method interfaces.
- Use contracts where they add value: classes instantiated through dependency
  injection, and code used by others (especially packages), letting
  implementations be switched out easily.
- Contracts are not always needed — don't introduce one everywhere by default,
  only where it is useful.

## Compliant

```php
public function __construct(
    private PaymentGateway $gateway,
) {
}
```

## Non-compliant

```php
public function __construct(
    private StripePaymentGateway $gateway,
) {
}
```

## Enforcement

Enforced by code review only. No sniff checks this standard.
