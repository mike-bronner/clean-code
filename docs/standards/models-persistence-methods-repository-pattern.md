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
[Pattern: Repository](https://github.com/mike-bronner/phpcs-rules/issues/6)
standard: that entry rules out dedicated `Repository` classes; this one
defines the persistence conventions the model itself carries instead.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. It is **not** enforced by a
PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint
time, with no type information. Whether a method name reveals business intent
(`addListingInfo()` vs `updateData()`), whether data parsing/assignment lives
inside the model, and whether a call receiver is actually an Eloquent model
are semantic judgements no single file's tokens can decide.

## Partial enforcement assessment

One narrow slice **is** catchable by a token-based sniff: a generic CRUD call
(`save`, `update`, `delete`, `create`) on a receiver **other than `$this`** —
e.g. `$user->save()` in a controller — is a token-visible signal that
persistence is being driven from outside the model. Calls on `$this` are the
blessed usage: the model's own descriptive methods calling `$this->save()`
internally.

A focused sniff issue has been opened for exactly that slice —
[#186](https://github.com/mike-bronner/phpcs-rules/issues/186) — warning
severity, since the check is name-based and PHPCS cannot see receiver types.
The complementary "no dedicated repository classes" slice already has its own
focused sniff issue,
[#126](https://github.com/mike-bronner/phpcs-rules/issues/126), spun out of
Pattern: Repository.

## What remains code review

The heart of the standard is naming and encapsulation quality: descriptive,
intent-revealing persistence methods with all data parsing/assignment inside
the model, organized into single-use traits. Whether `addListingInfo()` says
what actually happens — and whether the business domain stays decoupled from
the persistence domain — is a judgement about meaning, not tokens. That call
stays with code review.
