# Routes: Conventions (Do / Do Not)

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

## Compliant

```php
Route::resource('invoices', InvoiceController::class);
Route::post('invoices/{invoice}/send', SendInvoiceController::class);
```

## Non-compliant

```php
Route::get('invoices', function () {
    return Invoice::all();
});
Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Routes.DisallowClosureRoutes` | no |
| `CleanCode.Routes.DisallowNonResourceRoutes` | no |
| `CleanCode.Routes.NonInvokableSpecialAction` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/routes-conventions-do-do-not.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/routes-conventions-do-do-not.md)
