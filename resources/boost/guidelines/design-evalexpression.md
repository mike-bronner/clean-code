# PHPMD Design: EvalExpression

- Do not use `eval()`.
- Evaluated code is untestable, is a security risk, and hides from every static
  analysis tool. Write the code directly.

## Compliant

```php
if ($param === 42) {
    $param = 23;
}
```

## Non-compliant

```php
if ($param === 42) {
    eval('$param = 23;');
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Squiz.PHP.Eval` | no |

Code review checks the parts of this standard that the sniffs cannot see.
