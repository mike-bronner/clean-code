# Models: Persistence Methods (Repository Pattern)

- Laravel models are the de-facto persistence repository (especially
  Eloquent); do not create repository classes.
- Do not use generic Eloquent CRUD methods (`save()`, `update()`, `create()`,
  `delete()`, etc.) outside of the model; instead create descriptive methods
  that explain exactly what is happening.
- This decouples the business domain from the persistence domain; e.g. instead
  of `$user->save()`, create `$agent->addListingInfo($listingInfo);` and
  handle all data parsing/assignment in the method, calling `$this->save()`
  at the end.
- This is an adaptation of the repository pattern for Laravel models;
  splitting each model into single-use traits maintains organization while
  enforcing the pattern.

This is the model-side counterpart of the
[Pattern: Repository](https://github.com/mike-bronner/clean-code/issues/6)
standard: that entry rules out dedicated `Repository` classes; this one
defines the persistence conventions the model itself carries instead.

## Compliant

```php
$agent->addListingInfo($listingInfo);
```

## Non-compliant

```php
$agent->listing_info = $listingInfo;
$agent->save();
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Models.DisallowExternalPersistenceCalls` | no |

The sniff warns on a generic CRUD call made with `->` when the call chain does
not start at `$this`, `static`, `self` or `parent`, or at a class the file
states is not a model. So `$this->save()`,
`static::query()->where(...)->update(...)` and `$this->notes()->update(...)`
pass, and `$user->save()` and `User::query()->update(...)` warn. Every link
between the root and the call must be a method call: `$this->user->save()`
saves another model, and it warns.

A CRUD call made with `::`, such as `User::create(...)`, warns only when the
class resolves to a model namespace, through the file's namespace and its
`use` imports. The `modelNamespaces` setting holds those namespaces, and its
default is `App\Models`. The match is on whole namespace segments, so
`App\Models\Billing\Invoice` counts and `App\ModelsLegacy\Account` does not.
So `Carbon::create(...)`, `Storage::delete(...)` and
`Stripe\Customer::create(...)` pass. A call on the class from inside that
class passes too, as do `static::`, `self::` and `parent::`. A model outside
the configured namespaces is not checked on `::`.

A `->` chain gets the same check when the file states the class of its root.
Two roots state it:

- A static call on a named class, as in
  `Application::configure(...)->withRouting(...)->create()`.
- A parameter of the enclosing function, closure or arrow function that has a
  declared class type, as in `fn (PendingRequest $client) => $client->delete($url)`.

The chain passes when that class is outside `modelNamespaces`, and warns when
it is inside, so `User::where(...)->delete()` and
`function (User $user) { $user->save(); }` still warn. The file states a class
when the name is imported, written with a namespace, or used in a file that
declares a namespace. A bare name in a file with no namespace and no import,
such as `User` in `routes/web.php`, can be a global class alias, so it does
not count.

A chain rooted at the `DB` facade always warns, because a query-builder write
outside a model is what this standard forbids. That covers
`DB::table(...)->update(...)` with `DB` imported from
`Illuminate\Support\Facades\DB`, written fully qualified, or used through the
global `DB` alias.

Every other root keeps the first rule and warns. The parameter rule has these
limits:

- A nullable or union type passes only when every member is a stated class
  outside the model namespaces. So `?PendingRequest`, `PendingRequest|null`
  and a `null` default warn.
- A variadic, untyped or scalar-typed parameter warns, and so do `self`,
  `static` and `parent` types.
- The parameter must belong to the innermost function. A variable that an
  arrow function or closure captures from an outer function warns.
- Every use of the parameter inside the function must read a member, as in
  `$client->...` or `$client?->...`. Any other use warns, such as a
  reassignment, `return $client;`, or passing it to a call, because the sniff
  cannot rule out that the use changes the variable.

The sniff reads tokens only, so it cannot tell whether a class is a model. A
chain rooted at `$this`, `static`, `self` or `parent` passes in any class.

The bundled ruleset does not run the sniff on files under `tests/`,
`database/seeders/`, `database/migrations/` or `database/factories/`. Seeders,
migrations and factories write data on purpose, and that is not domain code.

Code review checks the parts of this standard that the sniffs cannot see.
