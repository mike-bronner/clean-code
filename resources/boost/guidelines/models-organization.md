# Models: Organization (member ordering)

1. List traits in alphabetical order, only one trait per line.
2. List the public, protected, and private properties, each group in
   alphabetical order.
3. List relationship methods in alphabetical order.
4. List getter and setter methods in alphabetical order.
5. List any other methods in alphabetical order.

A model reads like a reference sheet, not a diary. When every member sits where
its name says it should, finding one is a lookup rather than a search, and two
people adding a relationship a week apart put it in the same place.

## Compliant

```php
class Book extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['title'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
```

## Non-compliant

```php
class Book extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    use SoftDeletes, HasFactory;

    protected $fillable = ['title'];
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Models.MemberOrdering` | no |

Code review checks the parts of this standard that the sniffs cannot see.
