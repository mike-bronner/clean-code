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

## Enforceability — Tier 2 (custom sniffs + runtime safety check)

The standard **is enforceable**: Laravel ships a lazy-loading safety check
that turns violations into exceptions, and whether that check is switched on
is itself token-visible — so a sniff enforces the enforcement. The construct
the first rule prohibits outright, a populated `$with` property, is token
content in its own right, so a second sniff reports it directly.

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

### Custom sniff — the safety check is switched on

The safety check only protects a codebase if somebody enabled it, and that is
plain token content. Sniff: `CleanCode.Models.RequireLazyLoadingPrevention`
([#154](https://github.com/mike-bronner/phpcs-rules/issues/154)).

- **Detection** — the application service provider is reported when its class
  body contains no static call to `Model::preventLazyLoading()` or
  `Model::shouldBeStrict()`. The defect is an absence, so the warning is
  reported on the class declaration.
- **`shouldBeStrict()` counts** — Laravel's strict mode enables
  `preventLazyLoading()` as part of what it turns on.
- **The call may sit anywhere in the class** — delegating from `boot()` to a
  private `configureModels()` helper is a common provider idiom, and the
  check is equally enabled either way.
- **Configurable class list** — the watched class names are a public sniff
  property (`serviceProviderClasses`); the shipped default is
  `AppServiceProvider`. An application that boots the check from another
  provider adds that class to the list.
- **Warning severity, not error** — packages and non-application codebases
  have no such provider at all, and a project opts in by ruleset inclusion.
- **Boundaries** — the sniff is keyed to the class name, not to
  `extends ServiceProvider`: every application and package ships several
  providers (route, event, auth, package), and none of them is expected to
  enable the check, so keying off the parent would warn on all of them. A
  call is recognised by shape (`::name(`), so one written into a dead private
  method still satisfies the sniff, and an argument that disables the check in
  every environment (`preventLazyLoading(false)`) is not read.

### Custom sniff — no always-on eager loading

The first rule prohibits a construct that is plain token content: a populated
`protected $with = [...];` property on a model. Sniff:
`CleanCode.Models.DisallowAlwaysOnEagerLoading`
([#153](https://github.com/mike-bronner/phpcs-rules/issues/153)).

- **Detection** — a `$with` property declared on a class — in the class body,
  or promoted in its constructor — with a default that is an array literal
  holding at least one element, is reported on the property itself. Both array
  syntaxes count; an empty array, a non-array default, and no default at all do
  not.
- **Model-shaped parent required** — the class must `extend` a parent whose
  short name is `Model`, `Authenticatable`, `Pivot`, or ends in `Model`.
  `$with` is an ordinary property name any class may use, so the parent is what
  keeps the sniff off unrelated code. Only the short name is compared: the FQCN
  is not resolvable at lint time, but `extends Model` and
  `extends \Illuminate\Database\Eloquent\Model` both put `Model` in the file's
  tokens.
- **Configurable parent list** — the listed names are a public sniff property
  (`modelParentClasses`); a project whose base model is named something else
  adds it. The `*Model` suffix matches regardless of the list.
- **Warning severity, not error** — the standard says *avoid*, and a rare
  legitimate use exists (a tiny lookup relation genuinely needed on every
  load), so the sniff surfaces the smell without hard-blocking.
- **Boundaries** — the property name is matched case-sensitively, because PHP
  property names are and Eloquent reads `$with`. Only property declarations
  count, so a local `$with` assigned inside a method, an ordinary `$with`
  parameter of a method — `findWith(int $id, array $with = ['author'])` is an
  idiomatic signature, not a defect — and a nested anonymous class's own
  property are all left alone. An anonymous class is never a subject in its own
  right either, since PHPCS gives it a separate token this sniff does not
  register for. A constructor-promoted `public array $with = ['author']` *is*
  reported: it declares the property and its default, so it eager loads exactly
  like the long form. Dynamic assignment (`$this->with = …` in a constructor,
  `setEagerLoads()`) is invisible to a property-default check and stays code
  review. Half-written source is passed over rather than guessed at.

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
config above), one sniff keeps that check from being quietly left out, and the
other reports the always-on `$with` property the first rule prohibits. Judging
whether a given query loads the *right* relationships at the right place — and
preferring query-site `with()` over a later explicit `load()` (which the
runtime check does not forbid) — remains a query-site design call for code
review. So does eager loading configured at runtime rather than in a property
default, which no property-default check can see.
