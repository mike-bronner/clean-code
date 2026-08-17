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
  break `php artisan route:cache`. Enforced by the custom
  `CleanCode.Routes.DisallowClosureRoutes` sniff. Focused sniff issue:
  [#174](https://github.com/mike-bronner/phpcs-rules/issues/174).
  - **The nine registration verbs** — `get`, `post`, `put`, `patch`, `delete`,
    `options`, `any`, `match` and `fallback`. Each takes exactly one callable
    parameter, its action, so any closure sitting directly in the argument list
    is that action, wherever it sits: `Route::fallback()` puts it first,
    `Route::match()` third.
  - **Group callbacks excluded** — closures passed to `Route::group()`, or to a
    chained `->group()`, are not route actions and cache fine, so they are not
    flagged. `group` is simply not one of the nine verbs.
  - **Direct arguments only** — a closure one level down, inside a nested call
    or an array literal, is not a route action. Neither is a closure declared
    inside the action closure's own body: a route action is reported once,
    however many closures the statement holds.
  - **Chained builders resolved** — `Route::middleware('auth')->get(…)` reaches
    the verb through a chain, and the chain is walked back to the facade.
  - **Error severity** — the "Do Not" is unconditional, and the breakage is
    mechanical rather than stylistic.
  - **Detection only** — replacing a closure action means writing a controller
    and deciding where it lives, which is a design decision rather than a
    mechanical rewrite.
  - **Boundary — no symbol resolution.** A sniff sees one file's tokens, so it
    cannot know which `Route` symbol is imported; it assumes the Laravel facade
    convention and matches on the class segment before `::` spelling `Route`,
    bare or fully qualified. Two consequences follow. An unrelated class also
    named `Route` false-positives — accepted in a Laravel-standards ruleset.
    And a router held in a variable (`$router->get(…)`) has no `Route::` to
    resolve to, so it is never seen at all.

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
- **Do: for special action routes, use invokable controllers.** The action
  argument carries the answer: a bare `FooController::class` is invokable and
  compliant, while an array action `[FooController::class, 'method']` (or the
  legacy `'FooController@method'` string) with a method outside the seven
  RESTful actions is a candidate violation. Focused sniff issue:
  [#249](https://github.com/mike-bronner/phpcs-rules/issues/249).
  - **False positive** — several related non-RESTful actions deliberately
    grouped on one controller read as violations on shape alone.
  - **False negative** — a bare `::class` action passes on shape; whether
    that class actually defines `__invoke()` lives in another file.
  - **False negative** — the "very rare" half of the bullet is a frequency
    judgement, and a file of thirty invokable special-action routes passes
    every token check while breaking the intent.

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
