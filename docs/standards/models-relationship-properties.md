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

## Enforceability — Tier 2 (custom sniff)

The violation's textual shape is token-visible: a chained property fetch
(`$book->author->name` — two or more consecutive object-operator property
fetches with no call parentheses between them) is exactly the traversal the
standard rules out. Sniff:
`CleanCode.Models.DisallowChainedPropertyFetch`
([#42](https://github.com/mike-bronner/phpcs-rules/issues/42), absorbing the
follow-up issue
[#188](https://github.com/mike-bronner/phpcs-rules/issues/188)).

- **Detection** — two or more consecutive plain property-fetch hops
  (`->` or `?->`, mixed freely) rooted in a variable or `$this`. One
  diagnostic per chain, on the pair that first completes it, so
  `$book->author->address->city` points at `author->address` — the first model
  that should carry an accessor. The message quotes the pair with the operator
  the source actually wrote, so a nullsafe hop reads back as `author?->name`.
- **Error severity** — the standard mandates the accessor, so the sniff calls
  `addError()`. `rules.xml` adds no `<severity>` or `<type>` override.
- **Detection only** — the fix is a new accessor method plus a default for the
  absent relationship, which cannot be synthesised from the tokens. Nothing
  here is auto-fixable.
- **Boundaries** — a method-call hop is a different access pattern, so it ends
  the segment it belongs to; property fetches after it are judged on their own
  (`$a->b()->c->d` flags `c->d`, `$a->b()->c` does not). A dynamic member name
  (`$a->{$b}`, `$a->$b`) ends its segment the same way. Array-access hops
  (`$a->b['x']->c`) and static-rooted chains (`Foo::bar()->baz->qux`) are out
  of scope. A grouping parenthesis around the root does not hide the chain —
  `($book)->author->name` and `($books[0])->author->name` are flagged, because
  what the group holds is what decides; by the same rule
  `(new Book())->author->name` and `(Book::query()->first())->author->name`
  stay out of scope, since neither wraps a variable. Source PHP itself would
  reject — a stray closing bracket or a non-identifier member name left
  mid-edit (`$a->b)->c->d`, `$a->5->b->c`) — is refused rather than guessed at,
  so a typo in progress cannot break the build over a chain that is not there
  yet. `tests/` is excluded via ruleset path scoping in `rules.xml` — test
  suites build object graphs inline and read straight through them.

### Known limitations

PHPCS has no type information, so the sniff matches a shape, not a
relationship. Expect these:

- **False positives on non-Eloquent object graphs.** A DTO, a `stdClass`, or
  a `json_decode()` result read as `$payload->data->id` has the same token
  shape as a relationship traversal and is flagged the same way.
- **The accessor's own body is flagged.** `getAuthorNameAttribute()` returns
  `$this->author->name` — the one place the traversal belongs. `$this`-rooted
  chains are flagged like any other, so the accessor needs the suppression
  below.
- **No detection of the equivalent violations that hide the shape.** A dynamic
  property name (`$book->{$relation}->name`, `$book->$relation->name`) is
  unreadable at token level, so it ends its segment like a method call does —
  that two-hop read is not flagged, though two plain hops *after* a dynamic one
  still are. Helper-based reads (`data_get($book, 'author.name')`) reach the
  same relationship and are invisible entirely.
- **A root held in a multi-expression group is not flagged.** A grouping
  parenthesis is only walked into when it holds one expression, so
  `($condition ? $book : $fallback)->author->name` — and the `??` and `match`
  forms of the same thing — are silent. The alternative is worse: with one
  static-rooted arm and one variable-rooted arm, reading the root out of a
  single arm makes the verdict depend on the order the arms are written in, and
  the two orders say the same thing. Assign the group to a variable first if you
  want the chain checked.

### Suppressing an accepted false positive

At error severity an unsuppressed false positive breaks the build, so suppress
it inline with a one-line justification:

```php
// phpcs:ignore CleanCode.Models.DisallowChainedPropertyFetch -- accessor body: the one place this traversal belongs
return $this->author->name
    ?? "";
```

## What remains code review

Whether a flagged chain actually crosses a model relationship, whether an
accessor with a safe default is the right remedy, and whether a suppression is
justified rather than convenient are judgement calls about types and intent.
Those stay with code review.
