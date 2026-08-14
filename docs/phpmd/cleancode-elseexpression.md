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

The sniff is **stricter than the PHPMD rule**, not equal to it. It also carries
the clean-code standard
[#14](https://github.com/mike-bronner/phpcs-rules/issues/14) —
docs/standards/conditionals-no-else-or-elseif.md — which bans `elseif` as well
and asks for an auto-fixer. So `elseif` is reported under a second message code
`.ElseIfFound`, and the mechanically safe subset of both keywords is fixable.
PHPMD reports neither. Running `phpcs` still covers everything `phpmd` would
report for this rule, which is what the mapping has to guarantee; it simply
reports more besides.

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
- **Auto-fixable in a narrow subset** — PHPMD fixes nothing, so this is one of
  the places the sniff goes beyond it. `phpcbf` rewrites an occurrence only
  when every branch before the keyword ends in a jump statement and the layout
  is the canonical `} else {` / `} elseif (…) {` one; everything else is
  reported and left alone. docs/standards/conditionals-no-else-or-elseif.md
  lists the gates.

### Why not `SlevomatCodingStandard.ControlStructures.EarlyExit`

`EarlyExit` is the obvious existing sniff for this rule and it is not enough.
It reports an else only where an early exit is mechanically available — where
the `if` branch or the `else` branch already ends in a jump statement — because
it also offers to perform the rewrite. PHPMD reports **every** else, jump or
no jump. No property on the sniff widens that; `ignoreStandaloneIfInScope`,
`ignoreOneLineTrailingIf`, and `ignoreTrailingIfWithOneInstruction` only narrow
it further.

Measured over `tests/fixtures/DisallowElseSniff/failing.php` as it stood at
#77, carrying ten else branches and no `elseif` shapes of its own:

| Tool | Reports |
| --- | --- |
| `CleanCode.Conditionals.DisallowElse` | 10 |
| PHPMD 2.15.0 `CleanCode/ElseExpression` | 8 |
| `SlevomatCodingStandard.ControlStructures.EarlyExit` | 2 |

#14 has since grown that fixture past those ten, so the numbers above are the
measurement that settled the choice rather than a count of the fixture as it
stands now. The comparison itself is unchanged: `EarlyExit` still reports only
where it can rewrite, and this sniff still reports every occurrence.

`EarlyExit` is deliberately **not** wired into `rules.xml` alongside the custom
sniff: it would add no detection and would double-report those two lines. #14
answered the remaining question — whether the fixable subset was worth carrying
— by adding a fixer to this sniff rather than by wiring `EarlyExit` in beside
it, so there is still nothing for that sniff to contribute.

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

### Where PHPMD stays silent and this sniff does not

`elseif` is **not** a violation of the PHPMD rule. Its rule fires on the else
scope — the third child of an `if`/`elseif` node — so an `if`/`elseif` chain
with no closing `else` produces nothing there. The two-word `else if` form goes
the same way, because PHP parses it as an `elseif`.

This sniff reports both, under `.ElseIfFound`. That is #14's standard rather
than PHPMD's rule, and it is the one place the two genuinely diverge in
detection: a project that wants PHPMD's boundaries exactly can
`<exclude name="CleanCode.Conditionals.DisallowElse.ElseIfFound"/>` and keep
`.Found`, which is why the `else` code was left spelled `.Found` when #14
widened the sniff. Both shapes sit in
`tests/fixtures/DisallowElseSniff/failing.php` and are pinned line by line in
`tests/Standards/DisallowElseTest.php`.

### One reporting difference that is not a divergence

PHPMD reports the violation at the line of the else *body's opening brace*;
this sniff reports it at the `else` keyword. The two differ only when the brace
sits on its own line, which PSR-12 — wired into `rules.xml` — already forbids.
