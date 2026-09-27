# Methods: No Null Arguments

- When calling methods with optional parameters, don't pass `null` into the
  methods; use named parameters instead.

## Compliant

```php
$this->notify($user, channel: 'mail');
```

## Non-compliant

```php
$this->notify($user, null, 'mail');
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Methods.NoNullArguments` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
