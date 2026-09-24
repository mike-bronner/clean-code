# Controllers: Route Model Binding

## Standard

- Controllers should auto-resolve models through route-model-binding by adding
  the model parameter to the method (even if unused), as adding it triggers
  the binding and makes it available in the Form Request class.
- Route-model binding can be customized in the `RouteServiceProvider` as
  needed.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff, partial)

One slice of this standard **is statically lintable**: the *negative* shape,
where a controller action resolves a model by hand from one of its own
parameters. `SomeModel::find($id)` inside a public `*Controller` method, with
`$id` a parameter of that method, is token-visible in the controller file
alone — a controller action's scalar parameters come from route segments, so
fetching a model with one says the parameter should have been the model
itself. Sniff: `CleanCode.Controllers.ManualModelResolution`
([#50](https://github.com/mike-bronner/clean-code/issues/50), superseding the
follow-up issue
[#169](https://github.com/mike-bronner/clean-code/issues/169)).

- **Detection** — a static `find()` or `findOrFail()` call on a class name,
  whose first argument is a bare variable naming one of the enclosing method's
  parameters, inside a public method of a class whose name ends in
  `Controller`. The argument may be written positionally or as a named
  argument (`find(id: $id)`); the label is stepped over.
- **Warning severity, not error** — the sniff cannot read the route table and
  cannot tell an Eloquent model from any other class carrying a static
  `find()`, so a report is a review candidate rather than a mandated fix.
- **Boundaries** — a literal, a property read (`$this->id`, `$request->id`) or
  a local variable is not a route parameter and stays silent, as do
  private/protected methods, non-`Controller` classes, relative scopes
  (`self::`, `static::`, `parent::`), variable class names (`$model::find()`),
  query-builder chains (`Model::where(...)->first()`) and instance-side or
  repository calls (`Model::query()->find()`, `$repository->find()`) — the
  last two are token-indistinguishable from any other `->find()`. Only the
  *first* argument is read, so a named argument moved out of first position
  (`find(columns: ['*'], id: $id)`) is an accepted false negative. Parameters
  are read from the enclosing *named* method, so a closure that inherits the
  route parameter through `use` is flagged correctly, at the cost of two
  accepted false positives: a closure or arrow function whose own parameter
  shadows the action's, and a public method of an anonymous class declared
  inside the action. A named function declared inside an action is *not* one
  of them — it is never routed to and inherits nothing, so it is judged on its
  own and stays silent. Magic methods are silent too — a route binds into a
  named action or into `__invoke()`, and every other `__`-prefixed method is
  called by the engine, so its parameters never carry a route segment. A
  constructor is the case this rules out most often: its parameters come from
  the container, promoted or not. `__invoke()` itself is still flagged, because
  a single-action controller *is* its `__invoke()`. Every case named here is
  pinned in `tests/Standards/ManualModelResolutionTest.php`.
- **Detection only** — replacing the lookup with a bound parameter also means
  editing the route definition in another file, so there is no mechanical fix.

The standard's *positive* requirement is **not** statically lintable: whether
a controller method should receive a bound model is defined by the route table
(`routes/*.php` segments such as `{user}`), which lives in a different file,
and a PHPCS sniff sees one file's tokens at a time. Detecting a *missing*
type-hinted model parameter would require correlating route segments with
method signatures, which is impossible file-locally.

## What remains code review

- The positive requirement: the type-hinted model parameter is present even
  when unused, so the binding fires and the model is available to a paired
  Form Request class.
- Whether a customized binding in `RouteServiceProvider` (custom key or
  resolution logic) is appropriate for the route.
- Correct pairing between the bound model and the Form Request that consumes
  it.
