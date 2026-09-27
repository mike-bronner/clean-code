# Pattern: Model-View-Controller (MVC)

MVC is a **recommended default, not a mandatory architecture**. A feature does
not have to be decomposed into separate Model, View, and Controller classes.
Other patterns are accepted — see
[Accepted alternative patterns](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/pattern-model-view-controller-mvc.md#accepted-alternative-patterns) below. This is
forward guidance for an architectural choice, not a retroactive compliance
requirement for code that is already written.

If the pattern is used, the roles are defined as follows:

- **Model** — usually the core business logic; models should have attributes
  handling most of it, and request processing takes the model into account to
  persist or retrieve data.
- **View** — can be a model, response class, view, or value object.
- **Controller** — the RESTful / `__invoke` naming check described under
  [Enforceability](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/pattern-model-view-controller-mvc.md#enforceability--tier-3-not-statically-enforceable) applies
  only where a Controller class already exists by convention (a class name
  ending in `Controller`); it never asks a feature to have one, and it does not
  examine a Livewire component. Where a controller is used, it should contain
  no logic; only handle the incoming request, pass it to the Request Form
  class, then pass those results to the Response class (or view), returning
  that as the outgoing response. It should always be RESTful, or `__invoke` for
  single-action controllers.

## Compliant

```php
public function store(StoreBookRequest $request): BookResponse
{
    return new BookResponse($request->book());
}
```

## Non-compliant

```php
public function store(Request $request): JsonResponse
{
    $book = new Book($request->all());
    $book->price = $book->price * 1.2;
    $book->save();

    return response()->json($book);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Controllers.NoCustomActions` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/pattern-model-view-controller-mvc.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/pattern-model-view-controller-mvc.md)
