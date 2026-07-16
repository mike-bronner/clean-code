# Models: Relationship Properties

## Standard

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

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural standard: whether a property fetch traverses an
Eloquent *relationship* — rather than a value object, DTO, or `stdClass`
graph — requires type information a token-based PHPCS sniff does not have.
It is enforced via code review and developer discipline, not a PHPCS sniff.

## Partial-enforcement assessment

The violation's textual shape is token-visible: a chained property fetch
(`$var->prop->prop` — two or more consecutive object-operator property
fetches without call parentheses) is exactly the anti-pattern the standard
names. A narrow heuristic sniff can flag that subset. Focused sniff issue:
[#188](https://github.com/mike-bronner/phpcs-rules/issues/188).

- **Detection** — chains of two or more consecutive property fetches
  (`$a->b->c`), suggesting the terminal value be exposed as an accessor
  attribute on the first model.
- **Warning severity, not error** — without type awareness the sniff cannot
  tell relationship traversal from legitimate nested object access, so it
  points at review candidates rather than mandating a fix.
- **Property fetches only** — chains through method calls (`$a->b()->c`) are
  a different access pattern and are out of scope for this heuristic.

## What remains code review

Whether a flagged chain actually crosses a model relationship, whether an
accessor with a safe default is the right remedy, and equivalent violations
the shape heuristic cannot see (dynamic property names, `data_get()`-style
helpers) are judgement calls about types and intent — those stay with code
review.
