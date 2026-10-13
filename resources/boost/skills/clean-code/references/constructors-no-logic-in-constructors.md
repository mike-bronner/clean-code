# Constructors: No Logic in Constructors

Constructors should not include any functionality or logic, but merely assign
values to object properties. If logic needs to be performed, that is an
indication the information passed in should actually be another object. Any code
in the constructor is parsed every time an object is created, regardless of
necessity, and can't be optimized. If only assignments are handled, optimization
can be controlled.

## Compliant

```php
public function __construct(
    private Money $total,
) {
}
```

## Non-compliant

```php
public function __construct(int $cents)
{
    $this->total = new Money(round($cents / 100, 2));
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Constructors.NoLogic` | no |

Code review checks the parts of this standard that the sniffs cannot see.
