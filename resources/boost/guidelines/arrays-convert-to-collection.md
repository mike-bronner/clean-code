# Arrays: Convert To Collection

- Whenever possible use collections for manipulation.

## Compliant

```php
$names = collect($users)
    ->map(fn (User $user): string => $user->name);
```

## Non-compliant

```php
$names = array_map(fn (User $user): string => $user->name, $users);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Arrays.ConvertToCollection` | no |

Code review checks the parts of this standard that the sniffs cannot see.
