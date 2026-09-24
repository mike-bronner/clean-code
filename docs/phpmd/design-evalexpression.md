# PHPMD Design: EvalExpression

## Rule

- Do not use `eval()`.

**Why:** an eval-expression is untestable, is a security risk, and hides code
from every static-analysis tool. Write standard code instead.

```php
// PHPMD (and this ruleset) flags this:
if ($param === 42) {
    eval('$param = 23;');
}
```

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 0.2)_

## Mapping — Tier 1 (existing sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/EvalExpression` | `Squiz.PHP.Eval` (message code `.Discouraged`) |

Enforced by `Squiz.PHP.Eval`, wired into the master ruleset (`CleanCode/ruleset.xml`) — no
custom sniff needed ([#107](https://github.com/mike-bronner/clean-code/issues/107)).
Running `phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does
not have to run separately for it.

- **Detection** — the sniff listens for the `eval` language construct and
  reports each occurrence at its own line, wherever it appears: inside a
  conditional (PHPMD's own example), as a statement, or as part of a returned
  expression. PHPMD has no threshold or configurable property for this rule, so
  there is nothing to tune.
- **Severity raised to error** — the sniff reports a *warning* out of the box.
  `CleanCode/ruleset.xml` overrides it with `<type>error</type>` so that `eval()` fails a
  `phpcs` run the way it fails a `phpmd` run. Left as a warning, `phpcs` would
  exit `0` on eval usage and the mapping would not actually replace `phpmd`.
- **Not auto-fixable** — matching PHPMD. There is no mechanical rewrite from an
  eval'd string into standard code; the replacement depends on what the string
  does.
- **Methods named `eval` are not flagged** — `$object->eval(...)`,
  `$object?->eval(...)`, and `Class::eval(...)` call a method that happens to
  carry a reserved word as its name (legal since PHP 7.0). They are not the
  language construct, and the sniff leaves them alone; the boundary fixture
  pins this.
