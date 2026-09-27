# Classes: Introspection / Type Casting

- Avoid introspection (checking the type of the class to determine the outcome
  of a condition), e.g. using `instanceof`. This creates tight coupling and
  introduces technical debt, as the object type should already be defined in
  the method parameter or class property. If you have loosely coupled code but
  use introspection, you introduce another point of failure. Reaching for
  introspection probably means logic should be encapsulated or refactored.

## Compliant

```php
public function notify(Notifiable $recipient): void
{
    $recipient->notify($this->message);
}
```

## Non-compliant

```php
public function notify(object $recipient): void
{
    if ($recipient instanceof User) {
        $recipient->notify($this->message);
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Classes.DisallowTypeIntrospection` | no |

Code review checks the parts of this standard that the sniffs cannot see.
