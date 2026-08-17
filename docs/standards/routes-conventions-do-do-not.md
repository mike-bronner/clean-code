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

## Enforceability — Tier 2 (partial: three sniffs, remainder code review)

The six bullets do not share one enforceability tier, so each is assessed on
its own. One bullet is fully token-visible, two are token-visible as heuristics
with known false positives and negatives, and three need symbol resolution a
single-file sniff does not have.

### Fully enforceable — lintable

- **Do Not: use closures in routes.** A closure (`T_CLOSURE`) or arrow function
  (`T_FN`) passed as the action argument to `Route::get`/`Route::post`/etc. is
  a high-confidence violation: closure actions cannot be serialized, so they
  break `php artisan route:cache`. Focused sniff issue:
  [#174](https://github.com/mike-bronner/phpcs-rules/issues/174).
  - **Group callbacks excluded** — closures passed to `Route::group()` are
    not route actions and cache fine, so they are not flagged.
  - **Error severity** — the "Do Not" is unconditional, and the breakage is
    mechanical rather than stylistic.
  - **Boundary** — the sniff cannot resolve which `Route` symbol is imported;
    it assumes the Laravel facade convention.

### Partially enforceable — heuristic sniffs with documented boundaries

Both sniffs below report **warnings**, not errors: each has a false-positive
shape the standard itself permits, so they point a reviewer at a line rather
than failing a build.

- **Do: always use resource routes that point to RESTful controllers.** In a
  routes file, a verb call (`Route::get`/`post`/`put`/`patch`/`delete`/
  `options`/`any`/`match`) registers a route outside the resource-route
  convention; `Route::resource()` and `Route::apiResource()` are the compliant
  shapes. Focused sniff issue:
  [#248](https://github.com/mike-bronner/phpcs-rules/issues/248).
  - **False positive** — the standard permits rare special-action routes, and
    those are registered with exactly the same verb call. The sniff reports
    them; a reviewer dismisses them.
  - **False negative** — "points to a RESTful controller" is a claim about
    another file. Whether the target controller implements the seven RESTful
    actions is not visible from the registration call.
  - **False negative** — the check is gated on the file path (a configurable
    property), so a route registered from a service provider or a package boot
    method is not seen. Widening the gate raises the false-positive rate on
    every non-routes file.
- **Do: for special action routes, use invokable controllers.** Enforced by
  the custom `CleanCode.Routes.NonInvokableSpecialAction` sniff
  ([#249](https://github.com/mike-bronner/phpcs-rules/issues/249)). The action
  argument carries the answer: a bare `FooController::class` is invokable and
  compliant, while an array action `[FooController::class, 'method']` (or the
  legacy `'FooController@method'` string) with a method outside the seven
  RESTful actions is a candidate violation.

  The sniff reads the action argument of `Route::get`, `post`, `put`, `patch`,
  `delete`, `options`, `any` and `match` — second for every verb but `match`,
  whose HTTP-methods array comes first and whose action is therefore third. An
  action naming one of the seven RESTful methods is *not* flagged: that shape
  is a resource route written longhand, which #248 reports at the verb call
  itself, and flagging it here would double-report one line.

  Five boundaries, all deliberate:
  - **False positive — a deliberately shared controller.** Several related
    non-RESTful actions grouped on one controller read as violations on shape
    alone. The sniff reports them; a reviewer dismisses them. That is why the
    rule is a warning and not an error.
  - **False negative — an invokable-looking class that is not invokable.** A
    bare `FooController::class` action passes on shape; whether that class
    really declares `__invoke()` lives in another file and needs project-wide
    symbol resolution.
  - **False negative — the "very rare" half of the bullet.** Frequency is not
    token-visible. A routes file holding thirty invokable special-action
    routes clears every check while plainly breaking the intent; that
    judgement stays with code review.
  - **Dynamic actions are skipped, not guessed at.** An action built from a
    variable, a call, a class constant or string interpolation —
    `[$controller, 'x']`, `[FooController::class, $method]`,
    `[FooController::class, self::ACTION]`, `"FooController@{$method}"` — is
    unreadable at token level. So is the associative `['uses' => …]` action
    shape, which is left alone rather than read by position.
  - **Symbol resolution assumes the Laravel `Route` facade.** As with #174 and
    #248, the receiver is matched on the literal token `Route`, so an aliased
    import cannot be resolved. Two consequences: an unrelated `Http::get()` is
    never mistaken for a route registration, and a verb reached through a
    chained builder (`Route::middleware('auth')->get(…)`) is not seen, because
    the verb is called on the returned object rather than on the facade.

  The check is gated on the file path by the sniff's own configurable
  `routeFilePatterns` property, which ships matching any path holding a
  `routes` directory segment. Without it every `Route::verb()` call in a
  service provider, a package boot method or a test would be in scope — the
  same false-positive flood #248's gate exists to prevent. Detection only:
  converting an action to an invokable controller means creating that class
  and moving the method into it.

### Not statically enforceable — code review only (Tier 3)

Each of these needs to resolve what a route *means*, which a routes file's
token stream does not carry.

- **Do: only associate routes with a single model.** A route's model
  association is not in the registration call — it is in the controller's
  queries and the bound parameters, across other files.
- **Do: name the controller and route after the model they act on.** Matching a
  name against a model requires project-wide symbol resolution to know which
  models exist; a sniff sees one file's tokens.
- **Do Not: create routes that do not relate to models.** Same limitation as
  above, and stated as an absence: proving a route relates to *no* model means
  reading every file it reaches.

## What remains code review

The three semantic bullets in full, plus the judgement inside each heuristic:
whether a flagged verb route is the standard's permitted rare exception or a
controller that should have been RESTful, and whether an invokable
special-action route is rare enough to stay. The sniffs narrow where a reviewer
looks; they do not make the call.

The assessment is recorded on
[#65](https://github.com/mike-bronner/phpcs-rules/issues/65), and each
enforceable slice is tracked as its own focused sniff issue — one sniff per
issue, per this repository's convention.
