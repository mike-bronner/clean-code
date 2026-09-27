# Controllers: Route Model Binding

- Controllers should auto-resolve models through route-model-binding by adding
  the model parameter to the method (even if unused), as adding it triggers
  the binding and makes it available in the Form Request class.
- Route-model binding can be customized in the `RouteServiceProvider` as
  needed.

## Compliant

```php
public function show(Invoice $invoice): View
{
    return view('invoices.show', ['invoice' => $invoice]);
}
```

## Non-compliant

```php
public function show(int $id): View
{
    $invoice = Invoice::findOrFail($id);

    return view('invoices.show', ['invoice' => $invoice]);
}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Controllers.ManualModelResolution` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/controllers-route-model-binding.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/controllers-route-model-binding.md)
