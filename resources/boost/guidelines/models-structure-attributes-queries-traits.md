# Models: Structure (Attributes/Queries traits)

- All attribute methods are extracted to an `Attributes` trait, and all query
  methods to `Queries` trait(s) (e.g. `App\Concerns\Attributes\Book`,
  `App\Concerns\Queries\Book`).
- A `BaseModel` holds shared concerns; models `use` their `Attributes` and
  `Queries` traits and stay lean (relationships, `$appends`, `$fillable`).
- Benefits: centralized maintenance, centralized caching/optimization, reduced
  technical and visual debt, and adoption of better DRY patterns.

## Compliant

```php
class Book extends BaseModel
{
    use Attributes\Book;
    use Queries\Book;
}
```

## Non-compliant

```php
class Book extends BaseModel
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at');
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Models.ModelMagicMethodLocation` | no |

Code review checks the parts of this standard that the sniffs cannot see.
