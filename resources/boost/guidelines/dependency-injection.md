# Dependency Injection

- Where possible, classes should be injected via the constructor, allowing
  resolution through Inversion of Control (IoC) and avoiding tight coupling
  between classes.

## Compliant

```php
public function __construct(
    private Mailer $mailer,
) {
}
```

## Non-compliant

```php
public function __construct()
{
    $this->mailer = new SmtpMailer();
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Classes.DisallowConstructorInstantiation` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/dependency-injection.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/dependency-injection.md)
