# Models: Relationship Properties

Do not query relationship properties on models directly. Instead expose the
relationship property as an attribute of the model itself, allowing a default
if the relationship does not exist and limiting interdependence of models.

For example, instead of `$book->author->name`, create a model attribute
`authorName` and call `$book->authorName`:

```php
public function getAuthorNameAttribute(): string
{
    return $this->author->name
        ?? "";
}
```

The accessor keeps callers coupled to one model instead of two — consumers of
`Book` no longer need to know `Author`'s shape — and gives the model a single
place to provide a safe default when the relationship does not exist, rather
than every call site guarding against `null`.

## Compliant

```php
$name = $book->authorName;
```

## Non-compliant

```php
$name = $book->author->name;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Models.DisallowChainedPropertyFetch` | no |

Code review checks the parts of this standard that the sniffs cannot see.
