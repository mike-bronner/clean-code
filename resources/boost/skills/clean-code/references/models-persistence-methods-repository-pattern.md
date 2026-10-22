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
not start at `$this`, `static`, `self` or `parent`. So `$this->save()`,
`static::query()->where(...)->update(...)` and `$this->notes()->update(...)`
pass, and `$user->save()`, `User::query()->update(...)` and
`DB::table(...)->update(...)` warn. Every link between the root and the call
must be a method call: `$this->user->save()` saves another model, and it warns.

A CRUD call made with `::`, such as `User::create(...)`, warns only when the
class resolves to a model namespace, through the file's namespace and its
`use` imports. The `modelNamespaces` setting holds those namespaces, and its
default is `App\Models`. The match is on whole namespace segments, so
`App\Models\Billing\Invoice` counts and `App\ModelsLegacy\Account` does not.
So `Carbon::create(...)`, `Storage::delete(...)` and
`Stripe\Customer::create(...)` pass. A call on the class from inside that
class passes too, as do `static::`, `self::` and `parent::`. A model outside
the configured namespaces is not checked on `::`.

The sniff reads tokens only, so it cannot tell whether a class is a model. A
chain rooted at `$this`, `static`, `self` or `parent` passes in any class.

The bundled ruleset does not run the sniff on files under `tests/`,
`database/seeders/`, `database/migrations/` or `database/factories/`. Seeders,
migrations and factories write data on purpose, and that is not domain code.

Code review checks the parts of this standard that the sniffs cannot see.
