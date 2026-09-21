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
standard, which the master ruleset (`CleanCode/ruleset.xml`) references
([#99](https://github.com/mike-bronner/phpcs-rules/issues/99)). Running `phpcs`
with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have to run
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
- **Nested scopes in the condition are part of the condition** — a condition may
  carry a closure, an anonymous class, an arrow function, or a `match` arm, and
  that scope may carry loops of its own. Every `count()` inside it is
  re-evaluated each time the condition is tested, so all of them are flagged.
  PHPMD agrees: its rule keeps the condition's `Expression` node and runs
  `findChildrenOfType('FunctionPostfix')` over the whole subtree, which includes
  a closure literal written in the condition. The one thing this sniff steps
  over is a nested `for`/`while`'s own *condition* — that loop is registered in
  its own right and reports its condition itself, so reading it twice would
  report the same call twice. Only the condition is skipped. The nested header's
  initialiser and increment, which the nested loop's own pass ignores by the
  same section rule, and the nested loop's body are all still read, because the
  enclosing condition is re-evaluated each pass and so are they. All of these
  shapes are pinned in
  `tests/fixtures/DisallowCountInLoopExpressionSniff/nested-loops-in-condition.php`.
- **Reported as an error** — as with `Squiz.PHP.Eval` and `VariableAnalysis`, so
  that a violation fails a `phpcs` run the way it fails a `phpmd` run. Left as a
  warning, `phpcs` would exit `0` and the mapping would not actually replace
  `phpmd`.
- **Not auto-fixable** — matching PHPMD. Hoisting `count()` out of the condition
  is not a safe mechanical rewrite: a loop that mutates the array as it iterates
  depends on the re-evaluation, so only the author knows whether hoisting
  preserves the intent.
- **Same-named methods are not flagged** — `$collection->count()`,
  `$collection?->count()`, `Collection::count(…)`, a qualified
  `App\Support\count(…)`, and a bare name a `use function` import redirects
  elsewhere all resolve to something other than the global function. So does a
  declaration of a method by that name, and so does `new count(…)`, since a
  class may share the short name. The compliant fixture pins every one of them.

  `namespace\count(…)` is the one qualified spelling that depends on where it is
  written: it resolves against the namespace in force, so it *is* the global
  function in a file that declares no namespace and somebody else's inside a
  named one. The sniff routes this question through
  `CleanCode\Helpers\FunctionCalls::isGlobalFunctionCall()` rather than
  answering it itself, so both directions are pinned — the reported one in
  `failing.php`, the silent one in `namespaced-relative.php`.
- **First-class callables are not flagged** — `count(...)` and `sizeof(...)`
  (PHP 8.1) build a `Closure` referring to the function rather than invoking it,
  so no array is counted and there is no per-iteration re-count to report. The
  exemption is narrow: `count(...$args)` is a real call with a spread argument
  and is still flagged.

## Why a custom sniff and not an existing one

No existing PHPCS or Slevomat sniff expresses this rule. Slevomat's catalogue
polices type hints, namespaces, and control-structure *syntax*; it carries no
rule about calls in a loop condition, and nothing in it can be configured into
one. Two bundled PHPCS sniffs are close, but neither matches — both were run
against a live PHPMD 2.15.0 on the same probe file.

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

Every difference below was confirmed by running `phpmd … design` against a probe
file containing each shape. PHPMD's rule is `ClassAware`/`TraitAware`/`EnumAware`
only, so it inspects loops in class, trait, and enum *methods* and reports
nothing for a loop in a free function or at file level; the probe therefore puts
every shape inside a class method. This sniff has no such restriction and judges
a loop condition wherever it appears — a difference in reach, not in what counts
as a violation.

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

### Looser: first-class callables

PHPMD reports `count(...)` and `sizeof(...)` — PHP 8.1's first-class callable
syntax — as calls in a loop condition. This sniff does not.

| Shape | PHPMD 2.15.0 | This sniff |
| --- | --- | --- |
| `while ($sizer = count(...))` | flagged | silent |
| `while (in_array(sizeof(...), $callables, true))` | flagged | silent |
| `for ($i = 0; $i < 1 && count(...); $i++)` | flagged | silent |
| `while (count(...$rows) > 0)` — a real spread call | flagged | flagged |

`count(...)` builds a `Closure` that *refers* to the function; it never invokes
it. No array is counted, nothing is re-counted per iteration, and there is no
array whose mutation could move the loop's termination point — so none of the
harm the rule exists to prevent is present. PHPMD matches the AST's
`FunctionPostfix` node without inspecting the argument list, so it reports a
call that does not happen; that is a false positive, and this sniff does not
replicate it.

The exemption is deliberately narrow: the ellipsis has to be the *whole*
argument list. `count(...$rows)` spreads an array into a real call, re-counts on
every pass like any other call, and stays flagged by both tools.

### Matched: nested scopes and nested loops in a condition

Not a divergence, recorded because the shapes look like one and the parity was
measured rather than assumed. Each was run through both tools; PHPMD reports the
loop *statement* and this sniff reports the call token, so the comparison is of
report counts.

| Shape | PHPMD 2.15.0 | This sniff |
| --- | --- | --- |
| `count()` in a `foreach` body in a closure in the condition | 1 | 1 |
| `count()` in a nested `for` body in a closure in the condition | 1 | 1 |
| `count()` in a nested `foreach` header in a closure in the condition | 1 | 1 |
| `count()` in a closure's `return` in the condition | 1 | 1 |
| `count()` in an `if` block in a closure in the condition | 1 | 1 |
| Nested `for` condition in a closure in the condition | 1 | 1 |
| Nested `while` condition in a closure in the condition | 1 | 1 |
| Nested `do`/`while` condition in a closure in the condition | 1 | 1 |
| `count()` in a nested `for`'s initialiser in a closure in the condition | 1 | 1 |
| `count()` in a nested `for`'s increment in a closure in the condition | 1 | 1 |
| Nested loop condition in an anonymous class method in the condition | 1 | 1 |
| Nested loop condition reached through an arrow function in the condition | 1 | 1 |
| Nested loop condition in a `match` arm in the condition | 1 | 1 |
| Two sibling loops, one `count()` each | 2 | 2 |

### Reported at a different position

PHPMD reports the violation against the loop *statement*, not the offending
call: a do-while is reported at its `do` line, and a `for` whose header spans
several lines is reported at the `for` line. This sniff reports at the
`count()`/`sizeof()` token itself, which is PHPCS convention and points at the
code to change.

Detection is unaffected — both tools flag the same set of loops. Only the line
and column of the diagnostic differ.
