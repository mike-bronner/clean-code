# Pattern: Model-View-Controller (MVC)

## Standard

MVC is a **recommended default, not a mandatory architecture**. A feature does
not have to be decomposed into separate Model, View, and Controller classes.
Other patterns are accepted — see
[Accepted alternative patterns](#accepted-alternative-patterns) below. This is
forward guidance for an architectural choice, not a retroactive compliance
requirement for code that is already written.

If the pattern is used, the roles are defined as follows:

- **Model** — usually the core business logic; models should have attributes
  handling most of it, and request processing takes the model into account to
  persist or retrieve data.
- **View** — can be a model, response class, view, or value object.
- **Controller** — the RESTful / `__invoke` naming check described under
  [Enforceability](#enforceability--tier-3-not-statically-enforceable) applies
  only where a Controller class already exists by convention (a class name
  ending in `Controller`); it never asks a feature to have one, and it does not
  examine a Livewire component. Where a controller is used, it should contain
  no logic; only handle the incoming request, pass it to the Request Form
  class, then pass those results to the Response class (or view), returning
  that as the outgoing response. It should always be RESTful, or `__invoke` for
  single-action controllers.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Accepted alternative patterns

### Livewire full-page and single-file components

A Livewire full-page component — equally, a single-file component — is an
accepted alternative to the Model-View-Controller split. One class both renders
the view and handles the request, in place of a separate Controller and View.
This is **not a violation of this standard**.

The separation-of-concerns principle behind MVC still holds, whichever pattern
is chosen. Business logic belongs in the Model layer, or in a dedicated,
testable class — never inlined into the request-handling layer. That applies
inside a Livewire component exactly as it applies inside a controller.
Loosening the class-per-role split does not loosen this floor.

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. Where business logic *belongs*,
whether a class is acting as a model, view, or controller, and whether a
controller merely routes a request through a Request Form class to a Response
class or view are judgements about roles and intent that a token-based PHPCS
sniff cannot verify. Enforcement is via code review and developer discipline.

Because the pattern is a recommended default rather than a requirement, no rule
here asks a feature to have a Model, a View, or a Controller class at all.

### Partial-enforcement assessment — yes, one narrow slice

The "Controllers should always be RESTful or `__invoke` for single-action
controllers" clause has a token-visible slice: in classes whose name ends with
`Controller`, public method names are visible to a sniff, so any public method
outside the seven RESTful resource actions (`index`, `create`, `store`, `show`,
`edit`, `update`, `destroy`), `__invoke`, or `__construct` can be flagged as a
non-RESTful action.

**That check applies only where a Controller class already exists by
convention.** The sniff matches a class purely on the `Controller` name suffix,
so a Livewire component is never examined, and neither is any other class that
carries no such suffix. A feature built without a Controller class reports
nothing. The check never asserts that a Controller has to exist.

The slice was spun out as focused sniff issue
[#167](https://github.com/mike-bronner/phpcs-rules/issues/167), which was
closed as a duplicate of
[#141](https://github.com/mike-bronner/phpcs-rules/issues/141). It ships as the
sniff `CleanCode.Controllers.NoCustomActions`, documented under
[Controllers: No Business Logic](controllers-no-business-logic.md).

## What remains code review

Everything else. Whether the Model actually owns the business logic, whether
the View is an appropriate model / response class / view / value object, and
whether a controller body — or a Livewire component's request-handling code —
is truly logic-free rather than merely RESTfully named are semantic calls about
responsibilities and data flow. A sniff sees one file's tokens, not the
architecture. Whether MVC or an accepted alternative is the right shape for a
given feature is a design call as well. Those calls stay with code review.
