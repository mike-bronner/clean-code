# Constructors: Primary + Named Constructors

- Use one primary constructor (`__construct`).
- Provide multiple secondary (named) constructors — static factory methods —
  for the different scenarios in which the object is created.
- Every named constructor makes use of the primary constructor, so
  initialization logic lives in exactly one place.

## Compliant

```php
public function __construct(
    private int $cents,
) {
}

public static function fromDollars(float $dollars): self
{
    return new self((int) round($dollars * 100));
}
```

## Non-compliant

```php
public static function fromDollars(float $dollars): self
{
    $money = new self();
    $money->cents = (int) round($dollars * 100);

    return $money;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Constructors.PrimaryConstructorDelegation` | no |
| `CleanCode.Constructors.DisallowCombinedConstructor` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/constructors-primary-named-constructors.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/constructors-primary-named-constructors.md)
