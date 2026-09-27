# Conditionals: Avoid Conditionals

Avoid conditionals where possible.

## Compliant

```php
public function isAdult(): bool
{
    return $this->age >= 18;
}
```

## Non-compliant

```php
public function isAdult(): bool
{
    if ($this->age >= 18) {
        return true;
    }

    return false;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Conditionals.AvoidConditionals` | no |
| `SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn` | yes |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/conditionals-avoid-conditionals.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/conditionals-avoid-conditionals.md)
