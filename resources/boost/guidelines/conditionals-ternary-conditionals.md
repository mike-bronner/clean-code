# Conditionals: Ternary Conditionals

- Use ternary operators instead of if-statements where possible.
- Do not nest ternary conditions; instead assign to variables or refactor to
  methods.

## Compliant

```php
$label = $isPaid ? 'Paid' : 'Due';
```

## Non-compliant

```php
$label = $isPaid ? 'Paid' : ($isVoid ? 'Void' : 'Due');
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.ControlStructures.RequireTernaryOperator` | yes |
| `CleanCode.Conditionals.DisallowNestedTernary` | no |

Standard: [docs/standards/conditionals-ternary-conditionals.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/conditionals-ternary-conditionals.md)
