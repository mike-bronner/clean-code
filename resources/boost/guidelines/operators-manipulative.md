# Operators: Manipulative

Manipulation operators should start on a new line in standalone statements, but
may be written on a single line when acting as parameters:

```php
$result = 4
    + 4;
$result = floor(4 + 4.1);
$string = "Hello"
    . Str::lower(", world!");

if (
    $isTrue
    && $isAlsoTrue
) {
    //
}
```

Common manipulation operators:

- Strings: `.`
- Math: `+`, `-`, `/`, `*`, `%`, `**`
- Logical: `&&`, `||`
- Bitwise: `&`, `|`, `^`, `~`, `<<`, `>>`

## Compliant

```php
$total = $subtotal
    + $shipping;
```

## Non-compliant

```php
$total = $subtotal +
    $shipping;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Operators.ManipulationOperatorPlacement` | yes |
| `CleanCode.Operators.OperatorLineBreak` | yes |
| `CleanCode.Conditionals.OneConditionPerLine` | yes |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/operators-manipulative.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/operators-manipulative.md)
