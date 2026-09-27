# Methods: Naming

From Robert Martin's *Clean Code*: methods should have verb or verb-phrase
names like `postPayment`, `deletePage`, or `save`.

- Name methods according to what they do or return; names should be
  self-documenting.
- Methods that perform an action should be a verb and not return anything.
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

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/methods-naming.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/methods-naming.md)
