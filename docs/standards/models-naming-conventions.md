# Models: Naming Conventions

## Standard

**Boolean returns:**

- Boolean properties should start with `is`, `has`, `should`, etc. (a yes/no
  question).
- Boolean methods checking a condition should be named
  `has<Condition in past tense>`.

**Query methods:**

- Methods returning a single instance should be prefixed with `find` + model
  name, e.g. `->findUserByName(string $name)`.
- Methods returning a collection should be prefixed with `get` + model name,
  e.g. `->getUsersByType(string $type)`.

**Attributes:**

- Use the "new" attribute implementation (Laravel accessor).
- Create attributes to expose properties of related models.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

No existing rule fits. `Squiz.NamingConventions.ValidVariableName`,
`Squiz.NamingConventions.ValidFunctionName`, and
`PSR1.Methods.CamelCapsMethodName` judge *casing* only, and Slevomat's
`Classes.Superfluous*Naming` sniffs judge class-name suffixes — none of them
reads a declaration's type to decide whether its name is right. So this
standard is enforced by the custom sniff
**`CleanCode.Naming.ModelNamingConventions`**
([#44](https://github.com/mike-bronner/phpcs-rules/issues/44)), wired in through
the `./CleanCode/ruleset.xml` reference in the master `rules.xml`.

Every check is **reporting-only**. Renaming an identifier changes every call
site, and converting a legacy accessor to the `Attribute` implementation is a
semantic rewrite — neither is safe for a fixer to perform.

### Which classes are inspected

Only Eloquent models. A class counts as a model when either signal holds:

- it is declared in a namespace with a `Models` segment (the Laravel default,
  `App\Models\…`), or
- it extends a recognised Eloquent base class — `Illuminate\Database\Eloquent\Model`,
  `Illuminate\Foundation\Auth\User` (the class conventionally imported as
  `Authenticatable`), `Illuminate\Database\Eloquent\Relations\Pivot`, or
  `Illuminate\Database\Eloquent\Relations\MorphPivot`.

The base class is compared **after** the name in `extends` has been resolved
through the file's imports (see [Resolution](#resolution) below), never as it is
written. The name as written is not a reliable signal in either direction:
`extends EloquentModel` under `use Illuminate\Database\Eloquent\Model as
EloquentModel;` *is* a model, and `extends Model` under
`use App\Support\ValueObject as Model;` is not — a project that owns a `Model`
class of its own aliases Eloquent's out of the way exactly like that.

By the same rule, an `extends Model` with no matching import resolves against
the enclosing namespace and is that namespace's own class, not Eloquent's:
outside `Illuminate\Database\Eloquent` itself, reaching Eloquent's `Model`
requires a `use` statement or a leading backslash.

Everything else in the tree is left alone: `fetchUser(): User` in a service
class is ordinary code, and holding it to the `find` prefix would be noise.

### The checks

| Code | Flags |
| --- | --- |
| `BooleanPropertyPrefix` | A `bool`-typed property whose name is not a yes/no question (`$published`), declared in the class body or promoted from a constructor parameter. |
| `BooleanMethodPrefix` | A `bool`-returning method whose name is not a yes/no question (`expired()`). |
| `FindMethodPrefix` | A method returning a single model instance without the `find` prefix (`fetchUser(): User`). |
| `FindModelName` | A `find` method that does not name the model it returns (`findByName(): User`). |
| `GetMethodPrefix` | A method returning a collection without the `get` prefix (`allComments(): Collection`). |
| `LegacyAttributeAccessor` | `getFooAttribute()` / `setFooAttribute()` — the superseded accessor style. |

The yes/no prefixes accepted are the auxiliary/modal family: `is`, `are`,
`was`, `were`, `has`, `have`, `had`, `can`, `could`, `should`, `shall`, `will`,
`would`, `must`, `may`, `might`, `does`, `did`, `needs`. A prefix only counts
when the next character starts a new word, so `isLand` passes and `island` does
not.

A return type is read as **a collection** when it resolves to a class whose
short name is `Collection`, `LazyCollection`, or `Enumerable`; as **a model**
when it is `self`, `static`, `$this`, or `parent`, or when it resolves into a
namespace carrying a `Models` segment. Nullable types are read as the type they
wrap (`?User` and `User|null` both describe a `User`).

### Resolution

Every name the sniff interprets — the base class in `extends`, and every return
type — is resolved before it is judged, and the model a `find` method must name
is taken from the resolved name too. So `allComments(): CollectionAlias` is a
collection when `CollectionAlias` imports one, and `findUserByName(): Client`
is correctly named when `Client` imports `App\Models\User` — the method is named
after the model it returns, not after a file-local alias.

Resolution follows PHP's own rules, in this order:

1. a **fully qualified** name (leading `\`) stands as written and consults
   neither the imports nor the enclosing namespace — `\DateTime` is the global
   class even in a file that aliases a model onto that name;
2. otherwise the name's **first segment** is resolved through the file's `use`
   statements (plain and group form), which covers both a short name (`User`)
   and a qualified one (`Domain\Models\Account` under `use App\Domain;`);
3. anything still unresolved is prefixed with the **enclosing namespace**,
   PHP's fallback for an unimported name.

`use function` and `use const` import from PHP's separate function and constant
tables, never a type, so they are skipped and can never redirect a return type.
That holds for each shape the marker takes: on one item of a mixed group
(`use App\Models\{User, function make};`), before a group prefix
(`use function App\Models\{build};`), and on a plain statement
(`use function App\Models\build;`).

### Behavior notes (asserted by the test suite)

- **`has<ConditionInPastTense>` is enforced as far as a linter can see it.**
  Past-tense morphology is not machine-checkable, so the sniff requires the
  yes/no prefix family that `has` belongs to and leaves the tense of
  `hasExpired()` vs. `hasExpire()` to code review.
- **The `find` + model-name check only fires when the model is knowable.**
  `self`, `static`, `$this`, and `parent` name no model, so those methods need
  the prefix and nothing more. The model name may appear anywhere after `find`
  — `findOldestUser(): User` passes.
- **A collection return type names no model**, so `GetMethodPrefix` checks the
  prefix only.
- **Relationship methods are untouched.** `comments(): HasMany` returns a
  relation, which is neither a model nor a collection.
- **The "new" attribute implementation is untouched.**
  `authorName(): Attribute` returns
  `Illuminate\Database\Eloquent\Casts\Attribute`, outside any `Models`
  namespace. Eloquent's own `getAttribute()` is not read as a legacy accessor
  either — there is no attribute name between `get` and `Attribute`.
- **Promoted constructor properties are checked like any other property.** The
  ruleset also references
  `SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion` (#47), so
  running the fixer rewrites class-body properties into promoted ones; a check
  blind to that shape would let `composer fix` erase its own findings.
  A plain parameter carries no visibility modifier, declares no property, and is
  left alone.
- **Magic methods and Eloquent override points are exempt**: anything named
  `__*`, plus `newCollection`, `newModelInstance`, `newFromBuilder`,
  `newInstance`, `newPivot`, `newRelatedInstance`, `replicate`, `fresh`, and
  `refresh` — renaming those would break the override.

## Deliberate blind spots

- **Untyped properties and methods with no return type are skipped.** There is
  no signal to check against; the
  [Type Hints and Return Types](type-hints-and-return-types.md) standard (#45)
  is what makes those types appear in the first place.
- **Genuine union and intersection return types are skipped** — there is no
  single type to reason about.
- **Only the listed Eloquent base classes are recognised, and only directly.** A
  project whose models extend a local `BaseModel` outside a `Models` namespace
  goes unrecognised — PHPCS reads one file at a time and cannot follow
  `BaseModel` to the Eloquent class behind it. Putting models under `App\Models`
  (or extending an Eloquent base directly) is what the rule keys on.
- **Booleans exposed through the endorsed `Attribute` syntax are not
  prefix-checked.** `active(): Attribute` returns an `Attribute`, not a `bool`,
  and the idiomatic closure inside it (`get: fn () => …`) is usually untyped —
  there is no declared `bool` to key the yes/no rule on.
- **The base-class signal applies to the enclosing class only, never to a return
  type.** A class *extending* `Model` is recognised as a model, but a method
  returning one (`fetchThing(): Thing`, where `Thing extends Model` elsewhere)
  is not: PHPCS reads one file at a time and cannot follow `Thing` to its
  declaration. A return type is judged by its namespace alone.
- **Traits and enums are not inspected**, only classes.
