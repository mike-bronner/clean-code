# Models: Persistence Methods (Repository Pattern)

## Standard

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

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff, partial)

The highest-signal slice of this standard **is statically lintable**: a
generic CRUD call (`save`, `update`, `delete`, `create`) on a receiver
**other than `$this`** — e.g. `$user->save()` in a controller — is a
token-visible signal that persistence is being driven from outside the model.
Calls on `$this` are the blessed usage: the model's own descriptive methods
calling `$this->save()` internally. Sniff:
`CleanCode.Models.DisallowExternalPersistenceCalls`
([#37](https://github.com/mike-bronner/clean-code/issues/37), superseding
the follow-up issue
[#186](https://github.com/mike-bronner/clean-code/issues/186)).

- **Detection** — an instance method call (`->` or `?->`) named from the
  configured list on any receiver other than `$this` is flagged, whether the
  receiver is a variable, a property, or a chained call.
- **Configurable method list** — the flagged names are a public sniff
  property (`persistenceMethods`); the shipped default is `create`, `delete`,
  `save`, `update`.
- **Warning severity, not error** — the check is name-based; PHPCS has no
  type information, so a same-named method on a non-Eloquent object (e.g. a
  client library's `save()`) triggers a false positive.
- **Boundaries** — static `Model::create([...])` stays out: at the token
  level it is indistinguishable from named constructors and factory APIs.
  `tests/` is excluded via ruleset path scoping in `CleanCode/ruleset.xml` — factory
  chains (`User::factory()->create()`) make the pattern idiomatic there.
  Query-builder calls inside a model's own Queries traits share the token
  shape and will warn; scope those paths out in the ruleset or suppress
  inline. The complementary "no dedicated repository classes" slice has its
  own focused sniff issue,
  [#126](https://github.com/mike-bronner/clean-code/issues/126), spun out
  of Pattern: Repository.

## What remains code review

The heart of the standard is naming and encapsulation quality: descriptive,
intent-revealing persistence methods with all data parsing/assignment inside
the model, organized into single-use traits. Whether `addListingInfo()` says
what actually happens — and whether the business domain stays decoupled from
the persistence domain — is a judgement about meaning, not tokens. That call
stays with code review.
