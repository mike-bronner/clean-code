# Exceptions

## Standard

- Catch errors and exceptions as soon as possible; use type hinting and
  return types toward this.
- Create custom exceptions as much as possible, especially when catching for
  special handling.
- Always use `Throwable` for type-hinting exceptions, most often when
  catching.
- If the `$exception` is not used in the try-catch block, type hint without
  capturing the variable:

```php
try {
    //
} catch (Throwable) {
    //
}
```

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (Slevomat rules)

Two existing Slevomat sniffs satisfy the enforceable slices of this standard
exactly, so both are wired into `rules.xml` — no custom sniff needed.
`tests/Rules/ExceptionsRulesTest.php` guards this in two parts: a wiring test
parses the master `rules.xml` through PHPCS's real ruleset path and asserts
both rules are registered there, and per-sniff behaviour tests evaluate each
rule in isolation against its fixture in `tests/Rules/Fixtures/`.

- **`SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly`** — flags any
  reference to the general `\Exception` (fully qualified, imported, or inside
  a multi-catch), enforcing `\Throwable` as the catch-all. **Auto-fixable**:
  rewrites the reference to `\Throwable`. Deliberate allowances that match
  the standard's intent:
  - Catching specific or custom exceptions (`PaymentFailedException`,
    `\RuntimeException | \LogicException`) is compliant — the standard
    *encourages* custom exceptions for special handling.
  - `catch (\Exception)` is allowed when a later catch in the same `try`
    references `\Throwable` — the general catch-all is still present.
  - `extends \Exception`, `new \Exception`, and `instanceof \Exception` are
    outside the rule — defining or instantiating exceptions is not catching.
- **`SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch`** — flags a
  caught variable that is never used, requiring the non-capturing
  `catch (Throwable)` form (PHP ≥ 8.0; the sniff self-gates on the
  configured/runtime PHP version). **Auto-fixable**: drops the unused
  variable. Usage detection covers the catch body, string interpolation,
  arrow-function auto-capture, the `finally` block, and the rest of the
  enclosing scope after the `try` — none of those produce false positives.

## What remains code review

The catch-early / prefer-custom-exceptions guidance is semantic and is not
statically enforced: whether an exception *could* have been caught earlier,
whether a domain-specific exception class *should* exist for a given
`throw`/`catch` site, and whether type hints and return types are being used
to surface failures early are all judgement calls about design intent — they
stay with code review and developer discipline.
