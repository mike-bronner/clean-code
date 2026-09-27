# Arrays: Array Accessors (`data_get`)

- Always use `data_get()` to access arrays, instead of accessing their elements
  directly.

## Compliant

```php
$city = data_get($order, 'shipping.address.city');
```

## Non-compliant

```php
$city = $order['shipping']['address']['city'];
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Arrays.ArrayAccessors` | yes |

Code review checks the parts of this standard that the sniffs cannot see.
