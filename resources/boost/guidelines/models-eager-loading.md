# Models: Eager Loading

- Avoid eager loading relationships in the `protected $with = [];` variable as
  this could lead to data bloat.
- Try to explicitly load relationships at the point they are used using the
  `with()` method on the eloquent query, instead of using the `load()` method
  later in the code.

**Takeaway:** relationships are loaded explicitly at the query site with
`with()` — never always-on via a populated `$with` property, and not
retroactively via `load()` after the query has already run.

## Compliant

```php
$books = Book::query()
    ->with('author')
    ->get();
```

## Non-compliant

```php
class Book extends Model
{
    protected $with = ['author'];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Models.RequireLazyLoadingPrevention` | no |
| `CleanCode.Models.DisallowAlwaysOnEagerLoading` | no |

Code review checks the parts of this standard that the sniffs cannot see.
