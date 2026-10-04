# Arrays: Array Accessors (`data_get`)

- Always use `data_get()` to read array elements, instead of accessing them
  directly. This includes an array held in a property:
  `data_get($order->tags, 'primary')`, not `$order->tags['primary']`.
- This standard does not cover property reads. A single property read such as
  `$order->reference` is compliant. A read through one object into another,
  such as `$book->author->name`, belongs to
  [Models: Relationship Properties](models-relationship-properties.md): add a
  model attribute (accessor) to the outer model and read that instead.
  `data_get()` is not the fix for a property chain.

## Compliant

```php
$city = data_get($payload, 'shipping.address.city');
$tag = data_get($order->tags, 'primary');
$reference = $order->reference;
```

## Non-compliant

```php
$city = $payload['shipping']['address']['city'];
$tag = $order->tags['primary'];
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Arrays.ArrayAccessors` | yes |

Earlier releases also flagged property reads, and told the author to use
`data_get()` for them. They now pass this sniff.
`CleanCode.Models.DisallowChainedPropertyFetch` reports chained property reads.

Code review checks the parts of this standard that the sniffs cannot see.
