# Code Style: Linters (config & no auto-formatter)

- Your editor must support PHPCS linting and be configured to use the
  `phpcs.xml` file in the root of the project, alerting you to style
  violations. Violations not covered by linters should be caught and fixed
  during review.
- Do not use any auto-formatter that corrects linter issues, as this blows out
  reviews and hides the actual changes made. Manual correction reinforces good
  coding habits.

## Compliant

```php
$total = $subtotal + $shipping;
```

## Non-compliant

```php
// @formatter:off
$total   = $subtotal + $shipping;
// @formatter:on
```

## Enforcement

| Sniff | Auto-fixable by `phpcbf` |
|---|---|
| `CleanCode.CodeStyle.NoFormatterDirectives` | no |

Code review checks the parts of this standard that the sniffs cannot see.

Standard: [docs/standards/code-style-linters-config--no-auto-formatter.md](https://github.com/mike-bronner/clean-code/blob/main/docs/standards/code-style-linters-config--no-auto-formatter.md)
