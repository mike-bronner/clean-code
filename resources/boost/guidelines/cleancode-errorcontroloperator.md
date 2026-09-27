# PHPMD CleanCode: ErrorControlOperator

- Do not use the error-control operator `@`.
- It hides every error on that expression, including the ones you did not
  predict. Ask the question directly instead: `??`, `isset()`, or a guard.

## Compliant

```php
$key = $settings[$name] ?? null;
```

## Non-compliant

```php
$key = @$settings[$name];
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.PHP.NoSilencedErrors` | no |

Code review checks the parts of this standard that the sniffs cannot see.
