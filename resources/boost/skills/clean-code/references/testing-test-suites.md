# Testing: Test Suites

- **Unit Tests**: concern only the class under test; rare in Laravel (most
  classes have external concerns), but strive for them as they are fastest.
- **Feature Tests**: use more than internal methods (database, other classes,
  HTTP, WebSockets) but do NOT traverse the internet. Third-party APIs are
  tested here via HTTP fakes, with an identical integration test that does not
  use fakes.
- **Integration Tests**: dedicated to requests that test external dependencies
  over the internet.

## Compliant

```php
namespace Tests\Feature;

it('charges the card', function (): void {
    Http::fake(['api.stripe.com/*' => Http::response(['paid' => true])]);

    expect((new Checkout)->charge())->toBeTrue();
});
```

## Non-compliant

```php
namespace Tests\Unit;

it('charges the card', function (): void {
    expect(Http::post('https://api.stripe.com/charges')->ok())->toBeTrue();
});
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Testing.TestSuiteNamespace` | no |
| `CleanCode.Testing.UnitTestExternalConcerns` | no |
| `CleanCode.Testing.NoInternetTraversal` | no |
| `CleanCode.Testing.NoHttpFakesInIntegrationTests` | no |

Code review checks the parts of this standard that the sniffs cannot see.
