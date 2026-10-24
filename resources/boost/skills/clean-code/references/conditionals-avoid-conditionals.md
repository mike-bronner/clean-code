# Conditionals: Avoid Conditionals

Avoid conditionals where possible.

A guard clause is the exception. The sniff does not report an `if` that has
no `else` or `elseif`, whose body is one `return` (with or without a value) or
one `throw`, and that more code follows in the same function or closure. An
`if` that runs other code before it returns still reports, and so does an `if`
that is the last statement of its function. Every `elseif`, ternary and
`switch` still reports. A guard written without braces still reports.

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
