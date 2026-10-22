# Methods: Naming

From Robert Martin's *Clean Code*: methods should have verb or verb-phrase
names like `postPayment`, `deletePage`, or `save`.

- Name methods according to what they do or return; names should be
  self-documenting.
- Methods that perform an action should be a verb and not return anything.
  This is command-query separation: a caller can then tell a command from a
  query by its name, and never has to guess what a returned value means.
- Methods that return objects should be nouns named after the object they
  return (optionally prefixed with adjectives); in Models this should always
  be attributes.
- Methods should read as an action being taken on the class.

## Compliant

```php
public function sendReminder(): void
{
    $this->mailer->send(new Reminder($this));
}
```

## Non-compliant

```php
public function sendReminder(): bool
{
    return $this->mailer->send(new Reminder($this));
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Naming.ActionMethodReturn` | no |

The sniff does not warn on a public `store()` or `update()` method of a
controller. Laravel's router calls these actions, not domain code, and their
return value is the HTTP response, which the action must produce. A webhook
action, for example, states its response with `response()->noContent()`.
The sniff treats a class as a controller when its name ends in `Controller`,
its namespace contains `Http\Controllers`, or it extends `Controller`. Every
other action-verb method on a controller, such as `setFoo()`, still warns, and
so does a `store()` or `update()` that is not public.

Code review checks the parts of this standard that the sniffs cannot see.
