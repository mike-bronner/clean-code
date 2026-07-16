# Pattern: Model-View-Controller (MVC)

## Standard

- **Model** — usually the core business logic; models should have attributes
  handling most of it, and request processing takes the model into account to
  persist or retrieve data.
- **View** — can be a model, response class, view, or value object.
- **Controller** — should contain no logic; only handle the incoming request,
  pass it to the Request Form class, then pass those results to the Response
  class (or view), returning that as the outgoing response. Should always be
  RESTful or `__invoke` for single-action controllers.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. Where business logic *belongs*,
whether a class is acting as a model, view, or controller, and whether a
controller merely routes a request through a Request Form class to a Response
class or view are judgements about roles and intent that a token-based PHPCS
sniff cannot verify. Enforcement is via code review and developer discipline.

### Partial-enforcement assessment — yes, one narrow slice

The "Controllers should always be RESTful or `__invoke` for single-action
controllers" clause has a token-visible slice: in classes whose name ends
with `Controller`, public method names are visible to a sniff, so any public
method outside the seven RESTful resource actions (`index`, `create`,
`store`, `show`, `edit`, `update`, `destroy`), `__invoke`, or `__construct`
can be flagged as a non-RESTful action. Focused sniff issue:
[#167](https://github.com/mike-bronner/phpcs-rules/issues/167).

## What remains code review

Everything else. Whether the Model actually owns the business logic, whether
the View is an appropriate model / response class / view / value object, and
whether a controller body is truly logic-free (rather than merely RESTfully
named) are semantic calls about responsibilities and data flow — a sniff sees
one file's tokens, not the architecture. Those calls stay with code review.
