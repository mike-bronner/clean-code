# PHPMD CleanCode: UndefinedVariable

- Define a variable before you read it.
- PHP reads an undefined variable as `null` and raises a warning at runtime. The
  usual cause is a typo in the name.

## Compliant

```php
public function total(array $lines): int
{
    $total = 0;

    foreach ($lines as $line) {
        $total += $line->amount;
    }

    return $total;
}
```

## Non-compliant

```php
public function total(array $lines): int
{
    foreach ($lines as $line) {
        $total += $line->amount;
    }

    return $totl;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | no |

Code review checks the parts of this standard that the sniffs cannot see.
