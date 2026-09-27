# PHPMD Design: DepthOfInheritance

- A class has fewer than 6 ancestors. The sniff reports it at 6 or more.
- Understanding a deep class means reading every ancestor first. A chain that
  deep usually models something that composition should express.

## Compliant

```php
final class InvoiceMailer
{
    public function __construct(
        private Mailer $mailer,
        private InvoiceRenderer $renderer,
    ) {
    }
}
```

## Non-compliant

```php
class A {}
class B extends A {}
class C extends B {}
class D extends C {}
class E extends D {}
class F extends E {}
class G extends F {}
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.Metrics.DepthOfInheritance` | no |

Code review checks the parts of this standard that the sniffs cannot see.
