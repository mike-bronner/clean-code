# PHPMD CleanCode: DuplicatedArrayKey

- An array literal does not declare the same key twice.
- The later entry wins, so the earlier one never exists at runtime. Keys that
  PHP casts to the same value count as the same key: `0` and `false`, `1` and
  `'1'`, `15` and `0xF`.

## Compliant

```php
return [
    'name' => $name,
    'email' => $email,
];
```

## Non-compliant

```php
return [
    'name' => $name,
    "name" => $email,
];
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Arrays.DuplicatedArrayKey` | no |

Code review checks the parts of this standard that the sniffs cannot see.
