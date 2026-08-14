# PHPMD CleanCode: ElseExpression

## Rule

- Do not write an `else` branch on an `if` statement.

**Why:** an else branch is always avoidable. An early return, a guard clause,
or a ternary says the same thing with one level less nesting and one branch
less to hold in your head.

```php
// PHPMD (and this ruleset) flags this:
public function bar($flag)
{
    if ($flag) {
        // one branch
    } else {
        // another branch
    }
}
```

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html)
(PHPMD CleanCode ruleset, since PHPMD 1.4.0)_

The rule has no thresholds and no configurable properties, so there is nothing
to tune on either side of the mapping.

## Mapping — Tier 1 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CleanCode/ElseExpression` | `CleanCode.Conditionals.DisallowElse` (message code `.Found`) |

Enforced by the custom `CleanCode.Conditionals.DisallowElse` sniff, which the
master ruleset (`rules.xml`) picks up through its `./CleanCode/ruleset.xml`
reference ([#77](https://github.com/mike-bronner/phpcs-rules/issues/77)).
Running `phpcs` with `rules.xml` therefore covers this rule, and `phpmd` does
not have to run separately for it.

- **Detection** — the sniff registers on the `else` keyword and reports each
  one at its own line and column, wherever it appears: in a method, in a plain
  function, in a closure, at file scope, and in the alternative (`else:`)
  syntax.
- **Severity raised to error** — a custom sniff calling `addError()` reports an
  error out of the box. That is deliberate rather than incidental: PHPMD fails
  a run on an ElseExpression violation, and a warning would leave `phpcs`
  exiting `0` on an else branch, so the mapping would not actually replace
  `phpmd`.
- **Not auto-fixable** — matching PHPMD. Turning an else into an early exit
  means moving statements between branches and inverting a condition. That is
  safe only in the narrow shapes where one branch already ends in a jump
  statement; see "Why not Slevomat EarlyExit" below.

### Why not `SlevomatCodingStandard.ControlStructures.EarlyExit`

`EarlyExit` is the obvious existing sniff for this rule and it is not enough.
It reports an else only where an early exit is mechanically available — where
the `if` branch or the `else` branch already ends in a jump statement — because
it also offers to perform the rewrite. PHPMD reports **every** else, jump or
no jump. No property on the sniff widens that; `ignoreStandaloneIfInScope`,
`ignoreOneLineTrailingIf`, and `ignoreTrailingIfWithOneInstruction` only narrow
it further.

Measured over `tests/fixtures/DisallowElseSniff/failing.php`, which carries ten
else branches:

| Tool | Reports |
| --- | --- |
| `CleanCode.Conditionals.DisallowElse` | 10 |
| PHPMD 2.15.0 `CleanCode/ElseExpression` | 8 |
| `SlevomatCodingStandard.ControlStructures.EarlyExit` | 2 |

`EarlyExit` is deliberately **not** wired into `rules.xml` alongside the custom
sniff: it would add no detection and would double-report those two lines.
[#14](https://github.com/mike-bronner/phpcs-rules/issues/14) ("Conditionals: No
else or elseif") owns the question of whether the fixable subset is worth
carrying separately.

### Where this ruleset is stricter than PHPMD

Two shapes are reported here that PHPMD misses. Both are the same avoidable
branch the rule exists to catch, so both are kept rather than suppressed for
parity. They are pinned by `failing.php` lines 15 and 86 and asserted in
`tests/Standards/DisallowElseTest.php`.

| Shape | PHPMD | This ruleset | Why PHPMD misses it |
| --- | --- | --- | --- |
| An `else` at file scope, outside any function or method | silent | reported | `ElseExpression` implements `MethodAware` and `FunctionAware` only, so it never visits code outside a function body |
| A braceless single-statement `else` | silent | reported | it matches a PDepend `ScopeStatement`, which is only built for a braced body |

The braceless case never reaches a consumer as a lone diagnostic, incidentally:
`Generic.ControlStructures.InlineControlStructure` (wired in for
[#9](https://github.com/mike-bronner/phpcs-rules/issues/9)) requires the braces
anyway.

### Where both tools stay silent

`elseif` is **not** a violation of this rule, in either tool. PHPMD's rule
fires on the else scope — the third child of an `if`/`elseif` node — so an
`if`/`elseif` chain with no closing `else` produces nothing at all. The sniff
matches that by not registering `T_ELSEIF`.

The two-word `else if` form is treated the same way, for the same reason: PHP
parses it as an `elseif`, PHPMD stays silent on it, and reporting it would
discriminate on spelling alone. A closing `else` *after* such a chain is a real
else scope and is reported. Both shapes sit in
`tests/fixtures/DisallowElseSniff/passing.php` and, in their reported-else
form, in `failing.php`.

Banning `elseif` outright is a separate, stricter clean-code standard —
[#14](https://github.com/mike-bronner/phpcs-rules/issues/14) — not part of this
PHPMD mapping.

### One reporting difference that is not a divergence

PHPMD reports the violation at the line of the else *body's opening brace*;
this sniff reports it at the `else` keyword. The two differ only when the brace
sits on its own line, which PSR-12 — wired into `rules.xml` — already forbids.
