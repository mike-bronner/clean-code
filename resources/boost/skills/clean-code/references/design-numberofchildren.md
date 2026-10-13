# PHPMD Design: NumberOfChildren

- A class has fewer than 15 direct subclasses. The sniff reports it at 15 or
  more.
- Every subclass changes when the parent changes. A parent with that many
  children cannot change without a survey of all of them. Prefer an interface
  and composition.

## Compliant

```php
interface Notification
{
    public function send(User $user): void;
}

final class WelcomeNotification implements Notification
{
    public function send(User $user): void
    {
    }
}
```

## Non-compliant

```php
abstract class Notification {}

class WelcomeNotification extends Notification {}
class InvoiceNotification extends Notification {}
// ... 13 more direct subclasses of Notification
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Metrics.NumberOfChildren` | no |

Code review checks the parts of this standard that the sniffs cannot see.
