# PHPMD Naming: BooleanGetMethodName

- A method that returns a boolean is not named `get…()`. Name it `is…()` or
  `has…()`.
- A getter promises a value. A yes-or-no answer should read as a question at
  every call site.

## Compliant

```php
public function isActive(): bool
{
    return $this->active;
}
```

## Non-compliant

```php
public function getActive(): bool
{
    return $this->active;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.BooleanGetMethodName` | no |

Code review checks the parts of this standard that the sniffs cannot see.
