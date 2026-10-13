# Operators: Active

Active operators have a space between themselves and the object they are
acting on:

- Arithmetic assignment: `=`, `+=`, `-=`, `/=`, `*=`, `%=`, `**=`
- Bitwise assignment: `&=`, `|=`, `^=`, `<<=`, `>>=`
- Other assignment: `.=`, `??=`
- Logical: `and`, `or`, `xor`, `!`, `&&`, `||`
- String: `.`

## Compliant

```php
$total += $shipping;
$label = $first . $last;
$isOpen = ! $isClosed;
```

## Non-compliant

```php
$total+=$shipping;
$label = $first.$last;
$isOpen = !$isClosed;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Operators.BinaryOperatorSpacing` | yes |
| `Squiz.Strings.ConcatenationSpacing` | yes |
| `CleanCode.Operators.NotOperatorSpacing` | yes |
| `CleanCode.Operators.BooleanOperatorSpacing` | yes |
