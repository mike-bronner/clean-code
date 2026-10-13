# No Dead Code

- There should be no unused or commented code.
- A parameter the body never reads is unused code.
- A method that overrides an inherited signature may keep an unused parameter.
  Mark the override with `#[\Override]`, because the sniff cannot see a parent
  declared in another file.

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
