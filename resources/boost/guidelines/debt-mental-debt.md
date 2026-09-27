# Debt: Mental Debt

Mental debt is the mental cost required to read code.

- Write as few lines as possible; less code means parsing less.
- Carefully name classes, properties, and methods.
- Do not use abbreviations.
- Keep lines of code under 100 characters; exceed that by breaking to a new
  line.
- Remove code that doesn't accomplish anything.

## Compliant

```php
$elapsedDays = $startedAt->diffInDays($finishedAt);
```

## Non-compliant

```php
$ed = $sa->diffInDays($fa);
```

## Enforcement

Enforced by code review only. No sniff checks this standard.

Standard: [docs/standards/debt-mental-debt.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/debt-mental-debt.md)
