# Operators: Passive

Passive operators must sit flush against the object they are acting on — no
space between the operator and its operand:

- **Identity** — `+` (`+$a`)
- **Negation** — `-` (`-$a`)
- **Increment** — `++` (`$a++`, `++$a`)
- **Decrement** — `--` (`$a--`, `--$a`)
- **Error control** — `@` (`@file_get_contents(...)`)
- **Execution** — backticks (`` `ls` ``)
- **Access** — `[]` and `->` (`$a[0]`, `$a->b`)

Binary arithmetic (`$a + $b`, `$a - $b`) is a different operator and is out of
scope — its spacing is left untouched.

## Compliant

```php
$count++;
$balance = -$debt;
$name = $user->name;
$first = $items[0];
```

## Non-compliant

```php
$count ++;
$balance = - $debt;
$name = $user -> name;
$first = $items [0];
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.WhiteSpace.PassiveOperatorSpacing` | yes |
| `CleanCode.Operators.BinaryOperatorSpacing` | yes |
| `Generic.WhiteSpace.IncrementDecrementSpacing` | yes |
| `Squiz.WhiteSpace.ObjectOperatorSpacing` | yes |
| `Squiz.Arrays.ArrayBracketSpacing` | yes |
