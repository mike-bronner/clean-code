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

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced
by a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time.
This standard is about *where routes live* and *how controllers map to models
and views* — decisions spread across route registration, framework
configuration, the controller tree, and the `resources/views/` directory. No
single file's tokens carry enough of that picture to verify it.

## Partial enforcement assessment

No robust token-visible slice was found; no follow-up sniff issue is opened.

- **`API` route-namespace check** (the obvious candidate: a naming-prefix sniff)
  — in modern Laravel the `api` route namespace/prefix is applied *outside* the
  route file, where `routes/api.php` is registered (`bootstrap/app.php` or the
  `RouteServiceProvider`). The file a sniff would lint contains no token that
  carries the namespace decision. Checking the controllers' *PHP* namespace
  (`App\Http\Controllers\API\…`) instead would enforce a different rule than
  the one written — the standard constrains the *route* namespace, not the
  controller FQCN — and would presume a directory layout the standard does not
  mandate. Rejected.
- **Misplacement heuristic** — flagging `Route::prefix('api')` or
  `->name('api.')` inside `routes/web.php` is token-visible as a string match,
  but a fluent-chain string match cannot tell a genuine API route group from an
  unrelated route that legitimately uses those strings, and the include-pattern
  would hard-code the consuming app's file layout. High false-positive rate for
  a rare failure mode that review catches trivially. Rejected.
- **View clause and controller-per-model convention** — verifying that view
  routes carry *no* prefix requires knowing which routes are view routes (a
  semantic judgement), and one-controller-per-model-covering-all-its-views
  requires a cross-file mapping between controllers, models, and
  `resources/views/**`. A sniff sees one file's tokens; it cannot enumerate
  sibling directories. Not token-visible.

Resolution: **documentation-only** — the full standard remains enforced by code
review.
