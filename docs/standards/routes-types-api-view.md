# Routes: Types (API / View)

## Standard

**API:** API routes should be within an `API` route namespace.

**View:** should have no namespace/prefix (as the API routes do). Controllers
should be responsible for a single model but for all views pertaining to that
model, e.g. `/resources/views/reports/index.blade.php`, `create.blade.php`,
`show.blade.php`.

**Takeaway:** the two route types are separated by namespace — API routes are
grouped under `API`, view routes stay unprefixed — and each view controller
owns exactly one model, serving every view that belongs to it.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff, one slice of the standard)

Part of this standard is enforced by the custom
`CleanCode.Routes.ApiControllerNamespace` sniff, wired into the master
`rules.xml` via the CleanCode standard
([#66](https://github.com/mike-bronner/phpcs-rules/issues/66)). The rest stays
with code review — see [What remains code review](#what-remains-code-review).

### Why a custom sniff

The nearest existing rule is
[`SlevomatCodingStandard.Files.TypeNameMatchesFileName`](https://github.com/slevomat/coding-standard/blob/master/doc/files.md),
and it is closer than it first looks: run against
`app/Http/Controllers/API/InvoiceController.php` declaring
`App\Http\Controllers\InvoiceController`, it does report the mismatch. It is
still not a substitute:

- It only runs once configured with a `rootNamespaces` map of the **consuming**
  application's directory layout (`app` → `App`). This package ships a ruleset
  for arbitrary consumers, so hard-coding that map presumes a layout the
  standard does not mandate — the same objection that rejected the
  `routes/web.php` heuristic below.
- It enforces full PSR-4 name/path agreement for **every** type, so its
  coverage of this standard is incidental. Its diagnostic reads "class name
  does not match filepath": it neither names this standard nor separates the
  `API` grouping from any other directory mismatch, and it cannot be scoped to
  controllers.

Nothing else in the bundled or Slevomat catalogues comes closer — the remaining
namespace rules police *syntax* (`Namespaces.NamespaceDeclaration`,
`Namespaces.RequireOneNamespaceInFile`, `Namespaces.UnusedUses`), not the
API/view separation this standard draws.

### The sniffed rule is a proxy, not the standard itself

Read this before treating a clean run as proof of compliance.

The standard as written constrains the **route** namespace: where a route is
registered, and under which prefix. In modern Laravel that decision lives
*outside* every file a sniff lints — in `bootstrap/app.php` or the
`RouteServiceProvider`, where `routes/api.php` is registered — so no token in
any linted file carries it. A sniff cannot see it.

What *is* token-visible, in one file, is the controller's own declared
namespace, and the standard's separation surfaces there too: an API controller
belongs in `App\Http\Controllers\API\…`, a view controller does not. The sniff
enforces **that** — a recategorized, checkable sub-rule standing in for the
written one:

> A controller serving API endpoints is declared in a PHP namespace carrying an
> `API` segment matching its `Http/Controllers/API/…` path, e.g.
> `App\Http\Controllers\API\ReportController`. A controller serving views
> carries no such `API` namespace segment.

The substitution is deliberate and is the standard's own recategorization from
"not statically enforceable" to "partly enforceable", not an accident of
implementation. A codebase can satisfy the sniff and still register its API
routes in the wrong place; the sniff narrows where that mistake can hide, it
does not eliminate it.

### What the sniff checks

The check is symmetric, because either half alone is satisfiable by moving the
file rather than by fixing the problem:

- **`CleanCode.Routes.ApiControllerNamespace.MissingApiNamespace`** — the file
  sits under an `API` path segment but its namespace has no matching `API`
  segment. Reported at the `class` keyword.
- **`CleanCode.Routes.ApiControllerNamespace.UnexpectedApiNamespace`** — the
  class is declared in an `API` namespace but the file does not live under a
  matching `API` path segment. Reported at the `class` keyword.

Both sides are read **relative to the controller root**: only the segments
following the first `Controllers` segment count. A project checked out at
`/srv/api`, or a vendor package namespaced `Api\Http\Controllers\…`, would
otherwise read as API-everything. Both segments are matched
case-insensitively — the `API` grouping, so `API`, `Api` and `api` read alike,
and the `Controllers` root on the same terms — because the standard is about a
segment being present, not about how it is cased.

**Not flagged** — deliberately outside the rule:

- **A class that is not a controller** — neither its namespace nor its path
  carries a `Controllers` segment. `App\Services\API\Client` is left alone.
- **The class name** — only namespace segments are compared. An
  `ApiTokenController` in `App\Http\Controllers` is compliant.
- **Input with no path** — linting piped source without `--stdin-path` gives
  PHPCS the file name `STDIN`. There is no location for the namespace to
  disagree with, so the sniff says nothing rather than guessing.

### Not auto-fixable

Reconciling the two halves means either moving the file or rewriting its
declared namespace, and which of those is correct depends on the application's
layout rather than on anything in the file. There is no safe mechanical
rewrite, so the sniff is detection-only: it offers no fixer, and its fixture
directory carries no `autofixed.php`.

## What remains code review

Two parts of the standard are not token-visible and are enforced by review and
developer discipline:

- **The route-registration wording itself** — "API routes should be within an
  `API` route namespace", and the view half's "no namespace/prefix". This is
  about how routes are registered, which is what the sniffed controller-
  namespace rule stands in for rather than verifies. See
  [The sniffed rule is a proxy](#the-sniffed-rule-is-a-proxy-not-the-standard-itself)
  above.
- **One controller per model, serving all of that model's views** — verifying
  it needs a cross-file mapping between controllers, models and
  `resources/views/**`. A sniff sees one file's tokens; it cannot enumerate
  sibling directories or decide which model a controller is "responsible for".
  This clause has no automated coverage at all, by design.

Two heuristics were considered for the route-registration half and rejected:

- **Flagging `Route::prefix('api')` or `->name('api.')` inside
  `routes/web.php`** — token-visible as a string match, but a fluent-chain
  string match cannot tell a genuine API route group from an unrelated route
  that legitimately uses those strings, and the include-pattern would hard-code
  the consuming application's file layout. High false-positive rate for a
  failure mode review catches trivially.
- **Verifying that view routes carry no prefix** — requires knowing which
  routes are view routes, which is a semantic judgement rather than a token
  one.
