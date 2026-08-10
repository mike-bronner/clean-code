# PHPMD CodeSize: NPathComplexity

| | |
|---|---|
| **PHPMD rule** | `codesize.xml/NPathComplexity` |
| **PHPCS sniff** | `CleanCode.Metrics.NPathComplexity` (custom) |
| **Tier** | 2 — no existing sniff expresses the rule |
| **Severity** | error |
| **Auto-fixable** | no — report only, as in PHPMD |
| **Property** | `minimum`, default `200` |

Running this sniff replaces running `phpmd` for the `NPathComplexity` rule.

## What it measures

NPath is the number of acyclic execution paths through a function or method.
Where cyclomatic complexity **adds** 1 per decision point, NPath **multiplies**
the path counts of statements in sequence — two independent `if`s are 4 paths,
not 3 decision points. That is why a method can hold a modest number of branches
and still measure in the hundreds.

PHPMD does not compute the metric itself; it reads PDepend's `npath` metric and
compares it. The sniff recomputes the same number from PHPCS tokens.

## Counting rules

Every rule below is transcribed from PDepend's `NPathComplexityAnalyzer` and
then confirmed against a live PHPMD 2.15.0 run. PHPMD's own documentation states
none of them.

The body of a callable is a sequence: its NPath is the **product** of the NPath
of every statement in it. A statement that is none of the constructs below is
worth 1.

`B(x)` below is the boolean complexity of an expression — 1 for each `&&`, `||`,
`and`, `or`, or `xor` in it, plus the full NPath of any ternary in it. `N(r)` is
the NPath of a statement range.

| Construct | NPath |
|---|---|
| `if` | `B(cond) + N(then) + N(chain)`, plus 1 when the chain has no `elseif` and no `else` |
| `elseif` | same formula as `if`, so a chain nests rather than summing flat |
| `while` | `B(cond) + N(body) + 1` |
| `do … while` | `B(cond) + N(body) + 1` |
| `for` | `1 + B(cond) + N(body)`, where `cond` is the **middle clause alone** — PDepend sums only the loop's expression children, and the init and update clauses are not expressions, so a boolean operator in either is not counted |
| `foreach` | `B(expr) + 1 + N(body)` |
| `switch` | `B(expr)` plus `N(range)` for every `case` **and** `default` label |
| `try` | the sum of `N(range)` over the `try` block, every `catch`, and the `finally` |
| `? :` | `B(cond) + B(then) + B(else) + 2`; the short `?:` doubles `B(cond)` in place of the missing branch |
| `return` | `B(expr)`, or 1 when that is 0 |

A ternary's `B(cond)` is the first **child node** of the expression holding it,
not everything to the left of the `?`. So `$a && $b ? 1 : 0` does not count the
`&&` as part of the condition (the first child is `$a`), while
`($a && $b) ? 1 : 0` does (the first child is the whole parenthesised group) —
a live PHPMD run scores an `if` around the first 5 and around the second 6.

A `match` adds nothing for itself, and at statement level a boolean operator in
an arm body is not counted — but an arm body is still an ordinary expression in
the enclosing sequence, so a **ternary** in an arm multiplies into the callable
like any other. `match ($a) { 1 => $b ? 'x' : 'y', default => 'z' }` measures 2,
not 1.

That uncounted arm boolean belongs to statement position, not to `match`. Put
the same `match` in a condition or a `return` and PDepend sums the whole
expression, reaching the arm's operator after all — see **Position changes what
an expression is worth** below.

Worth nothing at all: `??`, `??=`, `?->`, `!`, `goto`,
`throw`, `yield`, `break`, and `continue`.

### Three results that surprise

- **`xor` counts.** PDepend ignores `xor` when computing cyclomatic complexity
  but counts it for NPath. `CleanCode.Metrics.ExcessiveClassComplexity` excludes
  it for that reason; the two sniffs disagreeing here is deliberate.
- **`return` double-counts a returned ternary's condition.** `return ($a && $b)
  ? 1 : 2;` scores 4, while `$c = ($a && $b) ? 1 : 2;` scores 3 — the condition
  is scored once as part of the return expression and again inside the ternary's
  own formula. This is a PDepend quirk, replicated deliberately.
- **A `switch` with no labels scores 0**, and 0 multiplied into the sequence
  zeroes the whole callable. A callable holding branches *and* an empty `switch`
  measures 0 and is therefore reported at no threshold.

## Position changes what an expression is worth

PDepend scores a **statement** with its statement visitor, which knows every
construct in the table above. It scores an **expression** with `sumComplexity()`,
which descends through the expression counting only boolean operators and
ternaries. An expression never reaches the statement visitor, so a construct
written inside one is worth whatever `sumComplexity()` finds and nothing more.

Two consequences, both confirmed against a live PHPMD 2.15.0 run and both
deliberately replicated:

| Written as | Measures |
|---|---|
| `$f = function () { if (…) {} if (…) {} if (…) {} };` — closure held by an assignment | 8: the body is a statement sequence, so the three `if`s multiply |
| `return function () { if (…) {} if (…) {} if (…) {} };` — the identical closure returned | 1: the body is inside an expression, so the `if`s are worth nothing |
| `$r = match ($a) { 1 => $b && $c, default => false };` — `match` at statement level | 1: the arm's `&&` is not counted |
| `return $q && match ($a) { 1 => $b && $c, default => false };` — `match` in an expression | 2: the arm's `&&` is summed with the rest of the expression |

The asymmetry looks like a bug and is not one. Making the expression walk
descend into a closure body or stop at a `match` boundary would score the second
and fourth rows 8 and 1 — the intuitive numbers, and the wrong ones. The
fixtures `closureInStatementPositionIsWalked()`,
`closureInExpressionPositionIsNotWalked()`,
`matchArmBooleanCountsInACondition()`, and `matchArmBooleanCountsInAReturn()`
pin all four rows so that change cannot land silently.

## One tokenizer gap the sniff covers

PHPCS 3.13.6 builds no scope for a `switch` whose subject holds a `match`:
`scope_opener` and `scope_closer` are both absent, and the labels' own
`conditions` skip the switch. The braced and the `:`/`endswitch` form are both
affected, and in the alternative form the *enclosing function's* `scope_closer`
lands on the `endswitch` as well, hiding every statement after it.

Read from the tokenizer alone such a `switch` looks label-less — which scores 0
and zeroes the whole callable, so the callable disappears at every threshold
rather than merely reading low. The sniff therefore walks the body for its
labels and recovers the bounds from the tokens (`switchBody()`), and takes a
callable's end from its opening brace's `bracket_closer`, which the tokenizer
fills in whether or not a scope was attached.

## What is measured, and what is skipped

| Declaration | Measured? |
|---|---|
| Named function, method of a class or trait | yes |
| A named function declared inside another callable | yes, separately — its body is excluded from the callable around it |
| A closure or arrow function | no, **not** separately: in statement position its statements belong to the enclosing callable and multiply into its score; in expression position only its boolean operators and ternaries count (see above) |
| An abstract method | yes; with no body there is no sequence, so it scores 1 |
| A method declared in an interface | no — PHPMD's method rules walk classes and traits, not interfaces |
| An anonymous class, and its methods | no — a live PHPMD run reports neither |

## The comparison

PHPMD reports a callable whose NPath is **at or above** `minimum`, not strictly
above it. Its `Rule\Design\NpathComplexity` returns early only when `$npath <
$threshold`. The sniff matches that, so a callable measuring exactly 200 is
reported by both tools at the default.

Reading `minimum` as a tolerated value would make this ruleset looser than PHPMD
at exactly that count, which is the one thing the mapping must not be.

## Why a custom sniff

No PHPCS, Slevomat, or PHPCSUtils sniff computes NPath. The nearest candidates
measure a different metric:

- `Generic.Metrics.CyclomaticComplexity` — adds one per decision point instead
  of multiplying paths, and ignores `xor`. It scores
  `multipliesSequentialBranches()` in the failing fixture 9 where NPath scores
  it 256.
- `Generic.Metrics.NestingLevel` — measures depth, not paths.
- `SlevomatCodingStandard.Complexity.Cognitive` — a third metric again, weighting
  nesting rather than counting paths.

None can be configured into NPath's semantics, so this is a custom sniff.

## Configuring it

The default matches PHPMD's, so nothing needs configuring. To change it:

```xml
<rule ref="CleanCode.Metrics.NPathComplexity">
    <properties>
        <property name="minimum" value="300"/>
    </properties>
</rule>
```

## Fixing a violation

There is no mechanical rewrite, which is why the rule is report-only in both
tools. Reduce the number of paths:

- Extract branch-heavy sections into their own methods — this is the one change
  that reliably helps, because it replaces a multiplied sub-sequence with a
  single statement worth 1.
- Replace a long `if`/`elseif` chain or a wide `switch` with a lookup table or
  polymorphism.
- Guard-clause early returns instead of nesting.

Splitting one 200-path method into two 15-path methods removes the multiplication
that produced the number.
