# PHPMD Design: DevelopmentCodeFragment

## Rule

- Do not commit calls to development-only debug functions.

**Why:** functions such as `var_dump()` and `print_r()` belong in a debugging
session, not in shipped code. A call that survives into the repository is a
forgotten debugging statement that leaks internals into output.

```php
// PHPMD (and this ruleset) flags this:
var_dump($data);
print_r($data);
```

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 2.3.0)_

## Mapping — Tier 1 (existing sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/DevelopmentCodeFragment` | `CleanCode.Debug.DisallowDebugFunctions` (message code `.Found`) |

Enforced by the custom `CleanCode.Debug.DisallowDebugFunctions` sniff, which the
master ruleset (`rules.xml`) already pulls in via
`<rule ref="./CleanCode/ruleset.xml"/>` — no ruleset edit was needed for this
mapping ([#86](https://github.com/mike-bronner/phpcs-rules/issues/86)). Running
`phpcs` with `rules.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **`unwanted-functions` parity** — PHPMD's default list is `var_dump`,
  `print_r`, `debug_zval_dump`, `debug_print_backtrace`. The sniff's
  `DEBUG_FUNCTIONS` constant covers all four, and additionally forbids `dd`,
  `dump`, and `ray` — the Laravel/Ray equivalents PHPMD has no knowledge of.
  The list is a constant rather than a configurable `<properties>` entry: this
  ruleset states one standard rather than offering a knob.
- **`ignore-namespaces` parity** — PHPMD's default is `false`, meaning a
  namespaced function of the same name is *not* treated as the global debug
  function. The sniff matches that: `App\Support\print_r($value)` and
  `namespace\debug_zval_dump($value)` resolve outside the global namespace and
  are left alone, while the fully-qualified global form `\print_r($data)` is
  flagged.
- **Not auto-fixable** — matching PHPMD. Deleting a debug call is a judgement
  about what the surrounding code was meant to do, so the rule is
  detection-only.
- **Only a real call to the global function is flagged** — `$debugger->print_r(...)`,
  `$debugger?->debug_print_backtrace(...)`, `Debugger::debug_zval_dump(...)`, a
  `function dump()` declaration, its return-by-reference form `function &ray()`,
  `new Dump(...)` and `new \print_r(...)`, an attribute such as `#[dd(1)]`, a
  bare name a `use function` import redirects to another namespace, and the name
  used as a string or property are none of them calls to the global function.
  The compliant fixture pins each of those shapes.
- **The detection is shared, not per-sniff** — the decision is
  `MikeBronner\CleanCode\Helpers\FunctionCalls`, which every sniff that flags a
  global function call routes through. A shape fixed there is fixed for all of
  them at once, which is the point: hand-rolled copies of this test had already
  drifted apart before it existed.
- **Return-mode calls are still flagged** — `print_r($data, true)` returns the
  dump instead of printing it, but it is the same development-only function and
  PHPMD does not special-case the second argument. Neither does the sniff.
