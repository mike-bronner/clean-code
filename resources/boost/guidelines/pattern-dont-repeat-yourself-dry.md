# Pattern: Don't Repeat Yourself (DRY)

- Code should be abstracted/refactored into small, manageable parts.
- Unrelated code can then use common functionality abstracted from other code
  paths.
- Don't abstract prematurely; only start abstracting when other code needs to
  perform the same logic.

## Compliant

```php
$this->notify($order->customer);
$this->notify($order->merchant);
```

## Non-compliant

```php
$message = new OrderShipped($order);
$message->locale = $order->customer->locale;
$order->customer->notify($message);

$message = new OrderShipped($order);
$message->locale = $order->merchant->locale;
$order->merchant->notify($message);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Pattern.AvoidDuplicateCodeBlocks` | no |

Code review checks the parts of this standard that the sniffs cannot see.
