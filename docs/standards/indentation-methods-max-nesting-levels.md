# Indentation: Methods (max 2 nesting levels)

## Standard

- Methods should have no more than **2 levels of nesting**.

**Why:** deep nesting often signals different concepts or concerns living in one
method and is a prompt to refactor — it reduces code complexity (mental debt)
and keeps concerns separated.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Enforced by **`CleanCode.Metrics.MethodNestingLevel`**, a custom sniff with a
single error code:

- **`MaxExceeded`** — a control structure nested more than 2 levels deep inside
  a function/method body. Each offending control structure is reported at its
  own line, so every excess nesting level is flagged individually; the message
  states the actual level found.

### How nesting is counted

One level is added per control structure — `if`/`elseif`/`else`, loops
(`for`/`foreach`/`while`/`do`), `switch`/`match`, `try`/`catch`/`finally`, and
anonymous functions (closures and arrow functions). Three structural details
keep the count faithful to how a reader sees indentation:

- **`case`/`default` do not add a level** — they belong to the enclosing
  `switch`, so an `if` inside a `case` is level 2, not level 3.
- **`elseif`/`else`/`catch`/`finally` sit at the same level** as the
  `if`/`try` they continue, rather than nesting beneath it — an `if … else`
  chain is one level, and statements inside `else` or `catch` are counted at
  the correct depth. Two-word **`else if`** (a bare `else` followed by a fresh
  `if`) is treated as a continuation of the same chain, exactly like one-word
  `elseif` — it is not reported a second time.
- **Nested named functions are excluded** — a nested *named* function
  declaration (`function helper() { … }` inside a method) is not counted: it
  defines a new named symbol rather than an inline block, and cannot legally
  recur inside a method body.

An anonymous function inside a method counts as a nesting level — both a closure
(`function () { … }`) and an arrow function (`fn () => …`) — for itself *and*
for whatever its body holds. The two forms measure identically, because they
read identically:

```php
if ($ready) { $run = fn () => (function () { … })(); }   // inner closure: level 3
if ($ready) { $run = function () { (function () { … })(); }; }   // also level 3
```

An over-nested arrow function is reported at the `fn` itself. The rule applies
only inside a function/method body; top-level script code is out of scope.

## Why not an existing sniff

Both candidates were run against this sniff's own `failing.php`, and both
evaluations are pinned by tests in `tests/Standards/MethodNestingLevelTest.php`
rather than left as prose — so a vendor upgrade that started covering this
standard reddens the suite instead of quietly outdating this page.

**`Generic.Metrics.NestingLevel`** reports **nothing** on that fixture. It
measures the maximum brace depth per function and warns above 5 / errors above
10; the deepest method there measures 4, so at the thresholds a consumer
receives it does not enforce a 2-level limit at all. Tightening the thresholds
does not close the gap either: it reports **once per function**, at the
declaration line, with only the *maximum* depth reached, and it counts raw brace
depth (`token['level']`) rather than the clean-code set of control structures
above — so it does not treat `case` or an `if…else` chain the way this standard
does. No configuration produces per-line reports over the required token set.

**`SlevomatCodingStandard.Complexity.Cognitive`** does fire — 11 times on the
same fixture — which is why the evaluation cannot stop at "it reports nothing".
It reports something else. It scores a whole-function *cognitive complexity*
metric — one number accumulating over all branching, boolean sequences, and
recursion — and warns above a configurable ceiling, once per declaration at the
declaration line. Its 11 lines and this sniff's 17 have **no line in common**.
It also misses a genuine 3-level violation (`matchInsideNestedLoops()`) while
scoring 10 for two methods that nest to different depths: no ceiling on that
number expresses "no deeper than 2 levels".

A custom sniff enforces the rule exactly instead.

## Not auto-fixable

Reducing nesting requires a semantic refactor — extracting a method, inverting a
condition, or introducing a guard clause — that a token rewriter cannot apply
safely without changing behaviour. The sniff therefore reports but does not fix;
the refactor stays with the developer.
