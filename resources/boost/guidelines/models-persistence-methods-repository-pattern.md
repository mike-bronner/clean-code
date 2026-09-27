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

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/models-persistence-methods-repository-pattern.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/models-persistence-methods-repository-pattern.md)
