# No Dead Code

- There should be no unused or commented code.

## Compliant

```php
public function total(): int
{
    return $this->subtotal;
}
```

## Non-compliant

```php
public function total(): int
{
    // return $this->subtotal + $this->legacyFee();
    return $this->subtotal;
}

private function legacyFee(): int
{
    return 0;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Squiz.PHP.CommentedOutCode` | no |
| `SlevomatCodingStandard.Namespaces.UnusedUses` | yes |
| `CleanCode.DeadCode.UnusedPrivateElements` | no |
| `CleanCode.DeadCode.UnusedFormalParameter` | no |
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/no-dead-code.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/no-dead-code.md)
