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

## Arguments by name and nested arrow functions

CleanCode runs the `VariableAnalysis` sniff through a subclass that fixes two
false reports in the upstream sniff. The sniff code stays the same, so an
existing exclude or ignore comment still applies.

- A by-reference argument passed by name defines its variable, as it does when
  passed by position: `preg_match(pattern: $p, subject: $s, matches: $m)`
  defines `$m`. This holds for PHP's own functions, which is where the
  standard's required named arguments meet by-reference parameters.
- An arrow function reads the variables of every arrow function around it:
  in `fn ($a) => array_map(fn ($b) => $a + $b, $items)`, `$a` is defined.

```php
public function words(string $line): array
{
    preg_match_all(pattern: '/\w+/', subject: $line, matches: $matches);

    return $matches[0];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | no |

Code review checks the parts of this standard that the sniffs cannot see.
