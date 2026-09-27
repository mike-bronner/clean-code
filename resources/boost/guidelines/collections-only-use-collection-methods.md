# Collections: Only Use Collection Methods

- Don't use generic PHP methods on collections; collections should be
  implemented for the built-in methods, as they are optimized and decouple us
  from direct PHP implementation.

## Compliant

```php
$hasAdmin = $users->contains('role', 'admin');
```

## Non-compliant

```php
$hasAdmin = in_array('admin', $users->pluck('role')->all());
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Collections.OnlyUseCollectionMethods` | yes |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/collections-only-use-collection-methods.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/collections-only-use-collection-methods.md)
