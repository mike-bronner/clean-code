# Classes: No Statics

- Avoid static classes. Classes are intended to be instantiated and
  identifiable. Static classes have no identity and are not true objects — a
  stow-away from the procedural era, little more than modern `GOTO` statements.

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

Standard: [docs/standards/classes-no-statics.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/classes-no-statics.md)
