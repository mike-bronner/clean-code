# Methods: Declared Parameters

- Methods should have a declared parameter list and not use a dynamic one.
  Magic methods are the exception.

## Compliant

```php
public function sum(int ...$amounts): int
{
    return array_sum($amounts);
}
```

## Non-compliant

```php
public function sum(): int
{
    return array_sum(func_get_args());
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Methods.DeclaredParameters` | no |

Code review checks the parts of this standard that the sniffs cannot see.
