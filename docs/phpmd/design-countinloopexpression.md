# PHPMD Design: CountInLoopExpression

## Rule

- Do not call `count()` or `sizeof()` inside a loop's condition expression.

**Why:** the condition is re-evaluated on every iteration, so the array is
re-counted every pass. Worse, a loop that modifies the array while re-reading
its size changes its own termination point mid-flight — the bug the rule exists
to catch. Take the size once, before the loop.

```php
// PHPMD (and this ruleset) flags this:
for ($i = 0; $i < count($items); $i++) {
    echo $items[$i];
}

// Compliant:
$total = count($items);

for ($i = 0; $i < $total; $i++) {
    echo $items[$i];
}
```

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 2.7.0)_

PHPMD's rule has no thresholds and no configurable properties, so there is
nothing to tune. Neither has this sniff.

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/CountInLoopExpression` | `CleanCode.ControlStructures.DisallowCountInLoopExpression` (message code `.Found`) |

Enforced by a custom sniff, picked up automatically through the CleanCode
standard, which the master ruleset (`rules.xml`) references
([#99](https://github.com/mike-bronner/phpcs-rules/issues/99)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

- **Detection** — the sniff registers `T_FOR` and `T_WHILE`. `T_DO` carries no
  parentheses of its own; a `do`/`while` loop's condition hangs off the trailing
  `while`, so those two tokens cover all three loop forms. `foreach` has no
  condition expression, so it is not registered — matching PHPMD.
- **Only the condition** — for a `while` (and a do-while) that is the whole
  parenthesised expression. A `for` header has three sections and only the
  middle one is the continuation test: `count()` in the initialiser runs once,
  and `count()` in the increment is not the test. PHPMD reports neither, and
  neither does this sniff.
- **Reported as an error** — as with `Squiz.PHP.Eval` and `VariableAnalysis`, so
  that a violation fails a `phpcs` run the way it fails a `phpmd` run. Left as a
  warning, `phpcs` would exit `0` and the mapping would not actually replace
  `phpmd`.
- **Not auto-fixable** — matching PHPMD. Hoisting `count()` out of the condition
  is not a safe mechanical rewrite: a loop that mutates the array as it iterates
  depends on the re-evaluation, so only the author knows whether hoisting
  preserves the intent.
- **Same-named methods are not flagged** — `$collection->count()`,
  `$collection?->count()`, `Collection::count(…)`, and a qualified
  `App\Support\count(…)` or `namespace\count(…)` all resolve to something other
  than the global function. So does a declaration of a method by that name. The
  compliant fixture pins every one of them.

## Why a custom sniff and not an existing one

Two bundled sniffs are close. Both were run against a live PHPMD 2.15.0 on the
same probe file; neither matches.

| Sniff | Verdict | Divergence |
| --- | --- | --- |
| `Squiz.PHP.DisallowSizeFunctionsInLoops` | Partial match | Also forbids `strlen()` in a loop condition, which PHPMD's rule does not cover. |
| `Generic.CodeAnalysis.ForLoopWithTestFunctionCall` | Partial match | Flags *any* function call in a `for` test — so it over-reports every unrelated call, and it registers `T_FOR` only, missing the `while` and `do`/`while` loops PHPMD reports. |

`Squiz.PHP.DisallowSizeFunctionsInLoops` is the closer of the two and gets the
`for`/`while`/do-while scoping right, but it cannot be narrowed to PHPMD's two
functions. Its `$forbiddenFunctions` list is `protected`, not a public property
a `<properties>` block can set, and `count`, `sizeof`, and `strlen` all report
under the single `.Found` code, so there is no code to `<exclude>` either.
Wiring it would import an unrelated rule the project never adopted, with no way
to turn it off — so the rule is implemented as a custom sniff instead.

## Divergences from PHPMD 2.15.0

Both differences below were confirmed by running `phpmd … design` against a
probe file containing each shape.

### Stricter: the spellings PHPMD misses

PHPMD matches the callable's name literally, which leaves two false negatives.
Both are the same defect written differently, so this sniff reports them.

| Shape | PHPMD 2.15.0 | This sniff |
| --- | --- | --- |
| `COUNT($items)` in a loop condition | silent | flagged |
| `SIZEOF($items)` / `Count($items)` in a loop condition | silent | flagged |
| `\count($items)` in a loop condition | silent | flagged |
| `\sizeof($items)` in a loop condition | silent | flagged |

PHP function names are case-insensitive, so `COUNT($items)` *is* `count($items)`,
in any mixture of case. A leading `\` names the same global function explicitly.
Every one of them re-counts the array on every iteration, so all are kept and
pinned in `tests/fixtures/DisallowCountInLoopExpressionSniff/failing.php`.

### Reported at a different position

PHPMD reports the violation against the loop *statement*, not the offending
call: a do-while is reported at its `do` line, and a `for` whose header spans
several lines is reported at the `for` line. This sniff reports at the
`count()`/`sizeof()` token itself, which is PHPCS convention and points at the
code to change.

Detection is unaffected — both tools flag the same set of loops. Only the line
and column of the diagnostic differ.
