# Classes: No Statics

- Avoid static classes. Classes are intended to be instantiated and
  identifiable. Static classes have no identity and are not true objects — a
  stow-away from the procedural era, little more than modern `GOTO` statements.
- **Exemption:** a static member a parent class forces does not report, because
  the class has no code fix for it. That is a static method or property an
  ancestor declares static and not private, and a static property no ancestor
  declares but an ancestor reads as `static::$name`. Nova 5's
  `Laravel\Nova\Resource` reads `static::$model` that way and never declares
  it, so every resource must. The ancestor must load through the project's
  autoloader. When it cannot, the member reports.

## Compliant

```php
class PriceFormatter
{
    public function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
```

## Non-compliant

```php
class PriceFormatter
{
    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Classes.DisallowStaticMembers` | no |

Code review checks the parts of this standard that the sniffs cannot see.
