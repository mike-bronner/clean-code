# Debt: Technical Debt

Technical debt is a collection of design or implementation constructs that are
expedient in the short term but set up a technical context that makes future
changes more costly or impossible. It can be created passively as the team
learns more about the problem, or deliberately for expediency.

**Takeaways:** address technical debt as soon as possible after it is
recognized; anyone on the team can identify technical debt.

## Compliant

```php
$total = $this->taxCalculator->totalFor($order);
```

## Non-compliant

```php
// TODO: replace this hard-coded rate before launch
$total = $order->subtotal * 1.2;
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `Generic.Commenting.Todo` | no |
| `Generic.Commenting.Fixme` | no |
| `CleanCode.Commenting.DebtMarkers` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/debt-technical-debt.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/debt-technical-debt.md)
