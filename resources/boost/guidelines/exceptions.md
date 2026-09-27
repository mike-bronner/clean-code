# Exceptions

- Catch errors and exceptions as soon as possible; use type hinting and
  return types toward this.
- Create custom exceptions as much as possible, especially when catching for
  special handling.
- Always use `Throwable` for type-hinting exceptions, most often when
  catching.
- If the `$exception` is not used in the try-catch block, type hint without
  capturing the variable:

```php
try {
    //
} catch (Throwable) {
    //
}
```

## Compliant

```php
try {
    $this->gateway->charge($invoice);
} catch (Throwable) {
    throw new PaymentFailed($invoice);
}
```

## Non-compliant

```php
try {
    $this->gateway->charge($invoice);
} catch (Exception $exception) {
    throw new PaymentFailed($invoice);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly` | yes |
| `SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
