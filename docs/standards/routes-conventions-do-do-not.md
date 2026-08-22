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

- **Do: always use resource routes that point to RESTful controllers.**
  **Shipped** as the custom sniff `CleanCode.Routes.DisallowNonResourceRoutes`
  ([#248](https://github.com/mike-bronner/phpcs-rules/issues/248)) — see
  [The resource-route sniff](#the-resource-route-sniff) below for the heuristic
  it implements and its six boundaries. In a routes file, a verb call
  (`Route::get`/`post`/`put`/`patch`/`delete`/`options`/`any`/`match`) registers
  a route outside the resource-route convention; `Route::resource()` and
  `Route::apiResource()` are the compliant shapes.
- **Do: for special action routes, use invokable controllers.** **Shipped**
  as the custom sniff `CleanCode.Routes.NonInvokableSpecialAction`
  ([#249](https://github.com/mike-bronner/phpcs-rules/issues/249)). The action
  argument carries the answer: a bare `FooController::class` is invokable and
  compliant, while an array action `[FooController::class, 'method']` (or the
  legacy `'FooController@method'` string, in either quoting style — a
  double-quoted one with nothing to interpolate is the same constant string)
  with a method outside the seven RESTful actions is a candidate violation.

  The sniff reads the action argument of `Route::get`, `post`, `put`, `patch`,
  `delete`, `options`, `any` and `match` — second for every verb but `match`,
  whose HTTP-methods array comes first and whose action is therefore third. A
  call that writes `action: …` is read by that name instead, in whatever order
  the names are written, so a named registration is reported exactly as the
  positional spelling of it is. An action naming one of the seven RESTful
  methods is *not* flagged: that shape is a resource route written longhand,
  which #248 reports at the verb call itself, and flagging it here would
  double-report one line.

  Seven boundaries, all deliberate:
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
    variable, a call, a class constant, string interpolation or a
    concatenation — `[$controller, 'x']`, `[FooController::class, $method]`,
    `[FooController::class, self::ACTION]`, `"FooController@{$method}"`,
    `'FooController@archive' . $suffix`,
    `[FooController::class, 'archive' . $suffix]` — is unreadable at token
    level. So is the associative `['uses' => …]` action shape, which is left
    alone rather than read by position.
  - **A literal is read in either quoting style, escapes evaluated.**
    `"App\\Http\\TagController@archive"` and
    `'App\Http\TagController@archive'` are the same string to PHP, so the same
    action is read out of both. Every escape sequence either quoting style
    defines is evaluated first, with one exception: the Unicode codepoint
    escape `\u{…}` is left as written and therefore matches neither name
    pattern, so `"FooController@arch\u{69}ve"` is skipped — a false negative
    for a spelling no route file uses.
  - **A heredoc or nowdoc action is skipped (false negative).** Its body is
    several tokens whatever it holds, so `<<<'ACTION'` carrying
    `FooController@archive` is passed over even though the content is fully
    literal. Unlike the dynamic shapes above this one is readable in
    principle; it is left unread because no route file spells an action that
    way.
  - **Symbol resolution assumes the Laravel `Route` facade.** As with #174 and
    #248, the receiver is matched on the literal token `Route`, case included,
    so an aliased import cannot be resolved and a differently cased spelling
    (`route::get(…)`) is read as another name. Two consequences: an unrelated
    `Http::get()` is never mistaken for a route registration, and a verb
    reached through a chained builder (`Route::middleware('auth')->get(…)`) is
    not seen, because the verb is called on the returned object rather than on
    the facade.

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

## The resource-route sniff

`CleanCode.Routes.DisallowNonResourceRoutes` carries the resource-route bullet
above. It is wired in through the CleanCode standard — no per-sniff
registration — and reports **warnings**, detection-only.

### The heuristic

The bullet is two claims. Whether the controller a route names really implements
the seven RESTful actions is a fact about *another file*, and PHP_CodeSniffer
sees one file at a time. That a route was registered with an HTTP verb rather
than as a resource is plain single-file token content, and that is the half
enforced.

The sniff warns on a static call to `Route::get`, `Route::post`, `Route::put`,
`Route::patch`, `Route::delete`, `Route::options`, `Route::any` or
`Route::match`, anchoring the warning at the **verb-method token itself**. The
compliant shapes — `Route::resource()`, `Route::apiResource()`,
`Route::resources()`, `Route::apiResources()` — are absent from that list and
never report. Neither do the modifiers that only wrap or configure other routes
(`Route::group()`, `Route::middleware()`, `Route::prefix()`, `Route::name()`,
`Route::domain()`, `Route::controller()`), nor `Route::fallback()`, which
registers the no-match handler.

Both the verb and the receiver are compared case-insensitively, PHP names being
case-insensitive, and the receiver is compared on its trailing namespace
segment — so `Route`, `\Route` and `Illuminate\Support\Facades\Route` all read
as the facade while `Router` and `ApiRoute` do not.

Warning severity is deliberate: the standard permits special-action routes in
the same breath as the rule ("this should be very rare"), and those are
registered with a verb call. A report is a prompt to look, not a proven defect.
Turning a verb route into a resource route means writing the seven RESTful
actions, so there is no mechanical rewrite and no auto-fix.

### Scope: route files only

PHP_CodeSniffer has no notion of "a routes file", so the sniff gates itself on
the linted file's own path through the public `routeFilePatterns` property —
`fnmatch` globs, shipped as `*/routes/*`. A file matching none of them produces
no diagnostics whatever its contents.

```xml
<rule ref="CleanCode.Routes.DisallowNonResourceRoutes">
    <properties>
        <property name="routeFilePatterns" type="array">
            <element value="*/routes/*"/>
            <element value="*/http/routes/*"/>
        </property>
    </properties>
</rule>
```

Two consequences of gating on the path are worth knowing. Piped input reports
its path as `STDIN`, which matches no shipped glob, so the sniff says nothing
about it. And the glob matches a `routes` segment anywhere in the absolute path
PHP_CodeSniffer hands over, so a checkout living under a directory called
`routes` widens the gate to the whole project — a project in that position
retunes the property.

### Boundaries

Six known limits, each a deliberate design decision rather than a defect. All
six are also recorded in the sniff's own class docblock.

#### Special-action routes report (false positive)

The standard permits rare special-action routes, and a special-action route is
registered with a verb call. A sniff cannot tell a legitimate rare exception
from a controller that should have been RESTful, so every such route reports;
warning severity is what stops that from blocking a build.
[#249](https://github.com/mike-bronner/phpcs-rules/issues/249) covers the
complementary check that the exception at least points at an invokable
controller.

#### `Route::fallback()` and framework-provided registrations (false positive)

`fallback` is deliberately outside the flagged list for exactly this reason: it
registers the no-match handler rather than a resource. Other package-provided
macros that register a route through a verb call are not distinguishable from an
application's own verb routes at token level, so a package's registrations
inside a route file would report like any other.

#### Routes registered outside a route file (false negative)

A verb call in a service provider, a package `boot()` method, or any other file
whose path does not match `routeFilePatterns` is never seen. Widening the gate
trades this for a much higher false-positive rate, because a verb-named static
call outside a route file is usually not a route at all.

#### The controller's actual shape (false negative)

"Points to a RESTful controller" is a claim about another file. The sniff reads
the registration call only. Whether the target controller implements the seven
RESTful actions — or whether a `Route::resource()` points at one that
implements none of them — needs project-wide symbol resolution and stays with
code review.

#### The `Route` facade is assumed, not resolved

Like [#174](https://github.com/mike-bronner/phpcs-rules/issues/174), the sniff
assumes the Laravel `Route` facade convention. It cannot resolve which `Route`
symbol an import actually binds from one file's tokens, so an unrelated class
named `Route` with a `get()` method reports, and a facade pulled in under an
alias (`use Route as Web;`, then `Web::get(...)`) does not.

#### Fluent-chained verb calls (false negative)

`Route::middleware('auth')->get(...)` and `Route::prefix('admin')->post(...)`
are not `Route::` static calls: the verb sits after `->`, on the registrar the
modifier returned. This token-level heuristic does not see them. Catching the
chained form means following a return value through an arbitrary chain, beyond
what [#248](https://github.com/mike-bronner/phpcs-rules/issues/248) asks for —
recorded here as a known false negative rather than pulled into detection scope.

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
