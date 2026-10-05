# Models: Relationship Properties

Do not query relationship properties on models directly. Instead expose the
relationship property as an attribute of the model itself, allowing a default
if the relationship does not exist and limiting interdependence of models.

For example, instead of `$book->author->name`, create a model attribute
`authorName` and call `$book->authorName`:

```php
public function authorName(): Attribute
{
    return Attribute::make(get: fn (): string => $this->author->name ?? "");
}
```

The accessor keeps callers coupled to one model instead of two — consumers of
`Book` no longer need to know `Author`'s shape — and gives the model a single
place to provide a safe default when the relationship does not exist, rather
than every call site guarding against `null`.

The accessor is where the chain belongs, so the sniff does not report a chain
inside one. An accessor is a method that returns
`Illuminate\Database\Eloquent\Casts\Attribute`, as above, including the
closures it passes to `Attribute::make()`. A legacy `get<Name>Attribute()`
method also counts. The accessor can be declared in the model or in a trait
the model uses. Everywhere else, a chain is reported.

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
