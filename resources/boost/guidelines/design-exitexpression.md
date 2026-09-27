# PHPMD Design: ExitExpression

- Do not use `exit` or `die` inside a function or method.
- Nothing can call the function and observe the result, because the process is
  gone. Return a value or throw, and keep process termination in the entry
  script.

## Compliant

```php
public function handle(int $param): void
{
    if ($param === 42) {
        throw new InvalidParameter($param);
    }
}
```

## Non-compliant

```php
public function handle(int $param): void
{
    if ($param === 42) {
        exit(23);
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.ControlStructures.DisallowExitExpression` | no |

Code review checks the parts of this standard that the sniffs cannot see.
