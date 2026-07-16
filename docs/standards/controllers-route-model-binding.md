# Controllers: Route Model Binding

## Standard

- Controllers should auto-resolve models through route-model-binding by adding
  the model parameter to the method (even if unused), as adding it triggers
  the binding and makes it available in the Form Request class.
- Route-model binding can be customized in the `RouteServiceProvider` as
  needed.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

The full standard **is not statically lintable**: whether a controller method
should receive a bound model is defined by the route table (`routes/*.php`
segments such as `{user}`), which lives in a different file, and a PHPCS sniff
sees one file's tokens at a time. Detecting the standard's positive
requirement — a *missing* type-hinted model parameter, even an unused one —
would require correlating route segments with method signatures, which is
impossible file-locally. Enforcement of the full standard is via code review
and developer discipline.

### Partial enforcement — narrow token-visible slice

One slice of the *negative* pattern is lintable inside the controller file
alone: manual model resolution from a route parameter. Focused sniff issue:
[#169](https://github.com/mike-bronner/phpcs-rules/issues/169).

- **Detection** — inside a class whose name ends in `Controller`, a public
  method calling `SomeModel::find($param)` or `SomeModel::findOrFail($param)`
  where `$param` is one of the method's own parameters. Scalar
  controller-action parameters are injected from route segments, so fetching
  a model by one manually signals the parameter should have been a
  type-hinted model instead.
- **Warning severity, not error** — the sniff can't see the route definition,
  so intentional deviations are flagged as review candidates rather than
  mandated fixes.

## What remains code review

- The positive requirement: the type-hinted model parameter is present even
  when unused, so the binding fires and the model is available to a paired
  Form Request class.
- Whether a customized binding in `RouteServiceProvider` (custom key or
  resolution logic) is appropriate for the route.
- Correct pairing between the bound model and the Form Request that consumes
  it.
