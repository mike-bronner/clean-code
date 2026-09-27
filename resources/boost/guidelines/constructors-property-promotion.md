# Constructors: Property Promotion

Use property promotion in constructors; avoid defining class properties
outside of the constructor.

## Compliant

```php
public function __construct(
    private Mailer $mailer,
) {
}
```

## Non-compliant

```php
private Mailer $mailer;

public function __construct(Mailer $mailer)
{
    $this->mailer = $mailer;
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion` | yes |

Standard: [docs/standards/constructors-property-promotion.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/constructors-property-promotion.md)
