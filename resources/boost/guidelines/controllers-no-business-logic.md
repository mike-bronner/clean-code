# Controllers: No Business Logic

- Controllers should only control the flow of requests and responses; all
  business logic should be extracted to Form Request classes and Response
  classes, leaving only a few lines per method.
- Controllers should be either RESTful or invokable; no custom actions.
  Reaching for custom actions is a code smell that the controller or model
  hasn't been named or extracted granularly enough.

## Compliant

```php
class InvoiceController
{
    public function store(StoreInvoiceRequest $request): InvoiceResponse
    {
        return new InvoiceResponse($request->invoice());
    }
}
```

## Non-compliant

```php
class InvoiceController
{
    public function markAsPaid(Invoice $invoice): RedirectResponse
    {
        $invoice->paid_at = now();
        $invoice->save();

        return back();
    }
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Controllers.NoCustomActions` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/controllers-no-business-logic.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/controllers-no-business-logic.md)
