# Routes: Conventions (Do / Do Not)

## Standard

**Do:**

- Always use resource routes that point to RESTful controllers.
- For special action routes, use invokable controllers; this should be very
  rare.
- Only associate routes with a single model.
- Name the controller and route after the model they act on.

**Do Not:**

- Use closures in routes, as they cannot be cached in
  `php artisan route:cache`.
- Create routes that do not relate to models.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This standard is architectural/semantic — whether a route maps to a RESTful
resource, relates to a single model, or is named after that model needs code
review, not a token check.

One narrow slice **is** token-visible: a closure or arrow function passed as
the action argument to `Route::get`/`Route::post`/etc. can be flagged
statically. Focused sniff issue:
[#174](https://github.com/mike-bronner/phpcs-rules/issues/174).

- **Detection** — a `T_CLOSURE`/`T_FN` action argument on a route-registration
  call is a high-confidence violation; closure actions break
  `php artisan route:cache`.
- **Group callbacks excluded** — closures passed to `Route::group()` are not
  route actions and are compatible with route caching, so they are not
  flagged.
- **Error severity** — the standard's "Do Not" is unconditional; closure
  actions actively break route caching rather than merely hinting at a
  missing abstraction.

## What remains code review

Everything except the closure slice: whether a route should be a resource
route to a RESTful controller, whether a special action genuinely warrants an
invokable controller, whether a route associates with exactly one model, and
whether the route and controller are named after that model are judgements
about intent and domain modelling — those calls stay with code review and
developer discipline.
