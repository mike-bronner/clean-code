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
- it extends a recognised Eloquent base class, matched on the short name:
  `Model`, `Authenticatable`, `Pivot`, `MorphPivot` — so
  `use Illuminate\Foundation\Auth\User as Authenticatable;` is recognised too.

Everything else in the tree is left alone: `fetchUser(): User` in a service
class is ordinary code, and holding it to the `find` prefix would be noise.

### The checks

| Code | Flags |
| --- | --- |
| `BooleanPropertyPrefix` | A `bool`-typed property whose name is not a yes/no question (`$published`). |
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

A return type is read as **a collection** when its short name is `Collection`,
`LazyCollection`, or `Enumerable`; as **a model** when it is `self`, `static`,
`$this`, or `parent`, or when it resolves — through the file's `use` statements
(plain and group form), through an already-qualified name, or through the
enclosing namespace — into a namespace carrying a `Models` segment. Nullable
types are read as the type they wrap (`?User` and `User|null` both describe a
`User`).

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
- **Model base classes are matched on their short name.** A project whose
  models extend a local `BaseModel` outside a `Models` namespace goes
  unrecognised; putting models under `App\Models` (or extending an Eloquent
  base directly) is what the rule keys on.
- **Traits and enums are not inspected**, only classes.
