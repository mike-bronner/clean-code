# Models: Eager Loading

## Standard

- Avoid eager loading relationships in the `protected $with = [];` variable as
  this could lead to data bloat.
- Try to explicitly load relationships at the point they are used using the
  `with()` method on the eloquent query, instead of using the `load()` method
  later in the code.

**Takeaway:** relationships are loaded explicitly at the query site with
`with()` — never always-on via a populated `$with` property, and not
retroactively via `load()` after the query has already run.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (runtime safety check + custom sniffs)

The standard **is enforceable**: Laravel ships a lazy-loading safety check
that turns violations into exceptions, and the token-visible slices around it
are sniffable.

### Runtime safety check

Enable `Model::preventLazyLoading()` in the application service provider:

```php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::preventLazyLoading(! $this->app->isProduction());
}
```

With the check active, accessing a relationship that was not eager loaded
throws `Illuminate\Database\LazyLoadingViolationException` (outside
production). A missing query-site `with()` surfaces immediately in development
and tests as a hard failure instead of degrading into silent N+1 queries —
models must be eager loaded, exactly as the standard requires.

### Custom sniffs

- **Safety check present** — the `preventLazyLoading` call in the service
  provider's `boot()` is single-file token-visible, so a sniff can verify the
  runtime enforcement is actually switched on. Focused sniff issue:
  [#154](https://github.com/mike-bronner/phpcs-rules/issues/154).
- **Non-empty `$with`** — a populated `protected $with = [...];` property on a
  model (the construct the first rule prohibits) is readable by single-file
  token analysis. Focused sniff issue:
  [#153](https://github.com/mike-bronner/phpcs-rules/issues/153).

### Rejected heuristic

- **`->load(` call check** — rejected. At token level the receiver's type is
  unknown: `load` is a common method name outside Eloquent (config loaders,
  file loaders, translators, third-party SDKs), so a string match on `->load(`
  cannot tell lazy eager loading on a model from an unrelated call. The rule
  is also a stated preference ("try to"), with legitimate `load()` uses such
  as conditionally loading relations on an already-retrieved model. High
  false-positive rate for a slice that review and the runtime check already
  cover.

## What remains code review

The safety check turns *implicit* lazy loading into a hard failure in
development and tests (it is disabled in production per the recommended
config above), and the sniffs cover the `$with` property and the check's
presence. Judging whether a given query loads the *right* relationships at the
right place — and preferring query-site `with()` over a later explicit
`load()` (which the runtime check does not forbid) — remains a query-site
design call for code review.
