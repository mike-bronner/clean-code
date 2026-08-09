# Models: Organization (member ordering)

Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)

## The standard

1. List traits in alphabetical order, only one trait per line.
2. List the public, protected, and private properties, each group in
   alphabetical order.
3. List relationship methods in alphabetical order.
4. List getter and setter methods in alphabetical order.
5. List any other methods in alphabetical order.

A model reads like a reference sheet, not a diary. When every member sits where
its name says it should, finding one is a lookup rather than a search, and two
people adding a relationship a week apart put it in the same place.

## How it is enforced

`CleanCode.Models.MemberOrdering`, a custom sniff, reports errors on the member
that is out of place:

| Code | Rule |
|---|---|
| `TraitOrder` | A trait use follows one that sorts after it. |
| `MultipleTraitsPerLine` | One `use` statement declares several traits. |
| `PropertyOrder` | A property follows one of the same visibility that sorts after it. |
| `PropertyGroupOrder` | A property's visibility group comes before the previous property's. |
| `RelationshipMethodOrder` | A relationship method follows one that sorts after it. |
| `AccessorMethodOrder` | A getter or setter follows one that sorts after it. |
| `MethodOrder` | Any other method follows one that sorts after it. |

Name comparison is case-insensitive throughout, so `$Total` sorts against
`$amount` the way a reader reads them.

### What counts as what

- A **relationship method** is a public method whose declared return type names
  an Eloquent relation — `HasMany`, `BelongsTo`, `MorphTo`, the rest of the
  concrete classes, and the `Relation` base a project's own relation class
  extends. Nullable, namespace-qualified, union, and intersection types all
  count, including the parenthesised DNF spelling — `(HasMany&Countable)|null`
  names a relation as surely as `HasMany` does. Without a
  declared return type the relation is invisible to a linter, and the method is
  ordered with the ordinary ones.
- A **getter or setter** is a method named `get` or `set` followed by a capital.
  `getter()` and `settle()` are ordinary methods.
- A public method returning a relation is a relationship method whatever it is
  called, so `getPosts(): HasMany` answers to rule 3, not rule 4.

### Scope and limits

- The sniff only speaks about a class extending a **model-shaped parent** —
  `Model`, `Authenticatable`, `Pivot`, or anything whose short name ends in
  `Model`. This is a models standard; an ordering rule on every class in a
  codebase would be a much larger rule than the one written above.
- **Promoted constructor properties are not ordered.** They are the
  constructor's signature, which a caller using named arguments depends on, not
  the class body's member list.
- **Magic methods are not ordered.** A constructor leads a class; it does not
  sort under "c".
- **The sequence between member kinds is not enforced** — neither traits before
  properties before methods, nor relationships before accessors before the
  rest. The standard states an ordering requirement *within* each kind; the one
  cross-group sequence it spells out is rule 2's public → protected → private,
  and that is enforced. A project that also wants a fixed sequence of member
  kinds can add `SlevomatCodingStandard.Classes.ClassStructure`, which does
  exactly that.
- **Detection only — there is no auto-fixer.** Reordering members means moving
  doc blocks, attributes, and preceding comments that are bound to a
  declaration by nothing but adjacency, and a trait conflict block
  (`use A, B { A::x insteadof B; }`) makes the trait uses genuinely
  order-dependent. A fixer that silently attaches a comment to the wrong member,
  or breaks working code, costs more than reordering by hand.

## Configuration

Both lists can be replaced from a consuming ruleset:

```xml
<rule ref="CleanCode.Models.MemberOrdering">
    <properties>
        <property name="modelParentClasses" type="array">
            <element value="Entity"/>
        </property>
        <property name="relationReturnTypes" type="array">
            <element value="Association"/>
        </property>
    </properties>
</rule>
```

A configured list **replaces** the shipped one. The `*Model` suffix rule on
parent names applies regardless of `modelParentClasses`.

## Existing rules evaluated

Verified against the pinned Slevomat version by
`tests/Standards/MemberOrderingTest.php`, not read off its documentation:

| Sniff | Covers | Does not cover |
|---|---|---|
| `SlevomatCodingStandard.Classes.ClassStructure` | The grouping half of rule 2, as part of a wider member-kind sequence | Alphabetical order — it has no notion of it anywhere |
| `SlevomatCodingStandard.Classes.TraitUseDeclaration` | The one-per-line half of rule 1 | Trait order; and it is unscoped, where this standard addresses models |
| `SlevomatCodingStandard.Classes.PropertyDeclaration` | Nothing here — it polices modifier order and whitespace inside one declaration | Any member's position relative to another |

Alphabetical order is four of the five rules and no shipped sniff speaks about
it, so the custom sniff was written rather than the tests bent to fit.

## Example

```php
class Article extends Model
{
    use HasFactory;
    use SoftDeletes;

    public string $slug = '';

    protected array $casts = [];

    protected string $table = 'articles';

    private bool $rendered = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function getExcerpt(): string
    {
        return Str::limit($this->body);
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function archive(): void
    {
        // …
    }

    public function publish(): void
    {
        // …
    }
}
```
