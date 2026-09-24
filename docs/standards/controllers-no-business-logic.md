# Controllers: No Business Logic

## Standard

- Controllers should only control the flow of requests and responses; all
  business logic should be extracted to Form Request classes and Response
  classes, leaving only a few lines per method.
- Controllers should be either RESTful or invokable; no custom actions.
  Reaching for custom actions is a code smell that the controller or model
  hasn't been named or extracted granularly enough.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (one slice enforced)

This is an **architectural standard**, and its core rule stays with code
review. One narrow slice is enforced: the custom sniff
`CleanCode.Controllers.NoCustomActions` warns on a public method in a
`*Controller` class that is not a resource action (see below). Everything
else is developer discipline.

Two properties put the core rule out of a sniff's reach:

- **"Business logic" is semantic, not a token shape.** PHPCS sees the same
  `T_STRING` / `T_OBJECT_OPERATOR` tokens whether a statement dispatches a
  response or calculates an invoice, so no token analysis can decide which
  statements *belong* in a controller.
- **The prescribed remedy is cross-file.** Extracting logic into Form Request
  and Response classes is an architectural move across several files, and a
  PHPCS sniff analyzes one file at a time, with no knowledge of what the
  extracted classes contain or whether they exist at all.

## Partial enforcement assessment

Each slice of the standard was assessed against what a single-file token
sniff can actually decide. One slice is feasible and is now enforced; the
other two are not.

- **Non-RESTful public method names — enforced by
  `CleanCode.Controllers.NoCustomActions`
  ([#141](https://github.com/mike-bronner/clean-code/issues/141)).** The
  "RESTful or invokable; no custom actions" rule maps onto data a sniff really
  has: a class name ending in `Controller`, plus the name and visibility of
  each method, are all plain single-file tokens. It is convention-dependent and
  cannot prove the class is actually routed, so it is a faithful slice rather
  than the whole rule.
- **Method-length cap — rejected.** "Leaving only a few lines per method"
  names no number, so any threshold would be invented rather than derived, and
  line count is a poor proxy for "no business logic": a long controller method
  can be pure flow control, and a three-line one can hide a pricing rule.
  Generic metrics tooling already covers method length.
- **The core rule itself — rejected.** Deciding whether a statement is
  request/response flow or business logic is the semantic judgement described
  above, and is not token-decidable.

## The sniff — `CleanCode.Controllers.NoCustomActions`

In a class whose name ends in `Controller`, every public method declared in
the class's own body earns a **warning** unless it is one of:

- the seven Laravel resource actions — `index`, `create`, `store`, `show`,
  `edit`, `update`, `destroy`;
- `__construct` or `__invoke`;
- a name in the `allowedMethods` property.

Method names are compared case-insensitively, because PHP's are.

### Configuring the allowlist

`allowedMethods` ships **empty**, so nothing beyond the list above is allowed
out of the box. An application that declares a framework hook — Laravel 11's
`HasMiddleware::middleware()` being the common one — names it in its own
ruleset:

```xml
<rule ref="CleanCode.Controllers.NoCustomActions">
    <properties>
        <property name="allowedMethods" type="array">
            <element value="middleware"/>
        </property>
    </properties>
</rule>
```

### Boundaries

Every one of these is accepted by design, not an oversight:

- **Convention-dependent.** Only a `*Controller` class name is examined, so a
  routed controller named otherwise is missed. The naming convention is itself
  part of the standard.
- **Routing is invisible.** The sniff cannot prove the class is routed, so a
  plain class suffixed `Controller` is examined as if it were one. Arguably a
  win: the name promises a controller either way.
- **Traits are invisible.** A method mixed in by a trait is declared in
  another file, and a sniff reads one file at a time. Interfaces and traits
  whose own names end in `Controller` are not examined at all.
- **Only the controller's own methods count.** A method of an anonymous class,
  or a named function, written inside an action belongs to that nested
  declaration, not to the controller.
- **Protected and private methods are never flagged.** The rule speaks about
  routable actions, and a routable action has to be public.
- **An unterminated class body is passed over in silence.** PHP_CodeSniffer
  resolves no scope for a class it never sees closed, so no method can be
  attributed to it.

Warnings, not errors, for the same reason the boundaries above exist: the
match rests on a naming convention, so a misread must not fail a build.
Detection only — extracting a custom action into its own controller rewrites
the routes that reach it, which is not a mechanical fix.

## What remains code review

Everything except the naming slice above. Whether a controller method only
moves a request to a response — and whether the logic it delegates has landed
in the right Form Request or Response class — is a judgement about meaning and
structure, not tokens. That call stays with code review.
