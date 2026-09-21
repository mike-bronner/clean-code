# PHPMD CodeSize: CyclomaticComplexity

## Rule

- A method or function stays below a configured number of independent paths
  through it.

**Why:** complexity is the count of decision points plus one for entering the
declaration. It is roughly the number of tests needed to cover the code, and
roughly the number of branches a reader has to hold in mind at once. PHPMD
grades 1–4 low, 5–7 moderate, 8–10 high, and 11 or more very high.

```php
// PHPMD (and this ruleset) flags this once the branches add up:
class Foo {
    public function example(int $n, bool $a, bool $b) {
        if ($a && $b) { }
        foreach ([] as $row) { }
        // ... and enough more branches to reach the report level
    }
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html#cyclomaticcomplexity)
(PHPMD CodeSize ruleset)_

## Mapping — custom sniff

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/CyclomaticComplexity` | `CleanCode.Metrics.CyclomaticComplexity` (message code `.Found`) |

`Generic.Metrics.CyclomaticComplexity` is the obvious candidate and it cannot
express this rule at any threshold. PHPMD reads PDepend's `ccn2` metric; the
Generic sniff counts a fixed token list of its own. The two disagree in **both**
directions, so no threshold offset reconciles them. Measured over identical
fixtures with live PHPMD 2.15.0 and PHP_CodeSniffer 3.13.6:

| Fixture | PHPMD `ccn2` | `Generic.Metrics.CyclomaticComplexity` | Cause |
| --- | --- | --- | --- |
| 10 × `if` | 11 | 11 | agree |
| 9 × `if` | 10 | 10 | agree on the count, disagree on the verdict (see below) |
| 1 × `if` + 10 × `&&` | 12 | 2 | the Generic sniff counts no boolean operator |
| 3 × `if` + `\|\|` + `and` + `or` + ternary | 8 | 5 | same cause |
| `switch` with 2 cases + `default` | 3 | 4 | the Generic sniff counts `default:` |
| `??` + `?->` + `match` | 1 | 4 | the Generic sniff counts coalesce, nullsafe, and match arrows |

So the rule is the custom `CleanCode.Metrics.CyclomaticComplexity` sniff, with
a token walk of its own — it neither extends nor delegates to
`Generic.Metrics.CyclomaticComplexitySniff`. It is registered automatically
from `CleanCode/Sniffs/` when the standard loads
([#88](https://github.com/mike-bronner/phpcs-rules/issues/88)). Running `phpcs`
with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not
have to run separately for it.

The measurement itself lives in `CleanCode/Support/CyclomaticComplexity.php`,
shared with `CleanCode.Metrics.ExcessiveClassComplexity`
([#87](https://github.com/mike-bronner/phpcs-rules/issues/87)), which sums the
same per-declaration count over a class's methods. PHPMD reads both rules off
the same PDepend measurement, so keeping one copy is what stops the two from
drifting apart on a construct only one of them has a fixture for.

- **Error severity** — the sniff reports errors, so an over-complex declaration
  fails a `phpcs` run the way it fails a `phpmd` run. No `<type>` override is
  needed in `CleanCode/ruleset.xml`.
- **Not auto-fixable** — matching PHPMD. Breaking a declaration into smaller
  ones is a design change, not a mechanical rewrite.
- **The report is attached to the declaration**, on the line the `function`
  keyword sits on.

## Configuration

The property is spelled exactly as PHPMD spells it and defaults to the value
PHPMD's `codesize.xml` ships, so an existing PHPMD configuration for this rule
transfers verbatim:

```xml
<rule ref="CleanCode.Metrics.CyclomaticComplexity">
    <properties>
        <property name="reportLevel" value="8"/>
    </properties>
</rule>
```

- `reportLevel` — the complexity at which a declaration is reported. Defaults
  to `10`.

A value that is not a usable positive whole number — `"ten"`, an empty
`<property>` element, `"0"`, `"-1"` — falls back to that default rather than
being used as written. A level of zero or below would report every declaration
in a codebase, including a one-line getter, so a configuration mistake degrades
to the documented behaviour instead of burying the report in noise.

PHPMD's other two documented properties, `showClassesComplexity` and
`showMethodsComplexity`, have no equivalent here and need none.
`showMethodsComplexity` is always on in practice, and `showClassesComplexity`
is vestigial in PHPMD 2.15.0: `PHPMD\Rule\CyclomaticComplexity` implements
`FunctionAware` and `MethodAware` and not `ClassAware`, so it never reaches a
class whatever that property says. **Class-level aggregate complexity is out of
scope for this sniff** — that measurement is a different PHPMD rule, mapped to
`CleanCode.Metrics.ExcessiveClassComplexity`.

## At or above the report level, not above it

**PHPMD reports a declaration whose complexity is at or above `reportLevel`,
not strictly above it.** `PHPMD\Rule\CyclomaticComplexity::apply()` is four
lines long and the guard is `if ($ccn < $threshold) { return; }`. At the default
of 10, a declaration measuring exactly 10 is a violation, and 9 is not.

phpmd.org grades 8–10 as "high" and 11+ as "very high", which reads like a
strict `>`. The sniff follows the tool rather than the prose, because reading
the level as a tolerated value would leave this ruleset silent on a declaration
PHPMD reports — and being looser than PHPMD at any count is the one thing this
mapping must not be.

`tests/fixtures/CyclomaticComplexitySniff/passing.php`
(`atOneBelowTheReportLevel()` at 9, silent) and `failing.php`
(`atExactlyTheReportLevel()` at 10, reported) pin the boundary from both sides,
and `configured.php` pins the same comparison at a configured level of 5.

## What counts

Each declaration starts at 1 and adds 1 per decision point in its body. Every
entry below was measured against a live PHPMD 2.15.0 / PDepend run, not read off
the documentation. PDepend's `CyclomaticComplexityAnalyzer` has exactly one
incrementing visitor per counted construct and no visitor at all for anything
else, which is why the second half of this table is as long as the first.

| Construct | Worth |
| --- | --- |
| entering the method or function | 1 |
| `if`, `elseif` (`else if` too — it is `else` followed by `if`) | 1 each |
| `while`, `for`, `foreach`, and a `do … while` loop | 1 each |
| `case` in a `switch`, including stacked labels sharing one body | 1 each |
| `catch`, however many exception types it lists | 1 each |
| `&&`, `\|\|`, `and`, `or` — one per operator, not per expression | 1 each |
| `?:` — both the full ternary and the short form | 1 each |
| `else`, `default`, `finally`, `xor`, `??`, `??=`, `?->`, `goto` | 0 |
| `match` and its arms | 0 |

Two of those are worth stating outright:

- **A chained boolean expression scores per operator.** `$a && $b && $c` is
  worth 2, not 1. `booleanOperatorChain()` in `failing.php` is one `if` and ten
  `&&`, measuring 12; `booleanChainRemoved()` in `passing.php` is the same `if`
  with the chain taken out, measuring 2.
- **Only the skipped token itself is skipped, not what is nested under it.** A
  `match` scores nothing, but a ternary or a boolean operator written inside one
  of its arms is an ordinary decision point and scores.
  `nestedInsideArms()` in `passing.php` measures 3 for exactly that reason.

`xor` was not measured when this rule was triaged, so it was measured during
implementation rather than assumed: PDepend has `visitLogicalAndExpression` and
`visitLogicalOrExpression` and **no visitor for xor**, and PHPMD 2.15.0 scores
`wordForms()` in `passing.php` — `(($a and $b) or $c) xor $d` — at 3. `xor` is
worth nothing.

## What the measurement covers

Also all verified against the live tools:

- **Named functions and methods only.** PHPMD's rule is `FunctionAware` and
  `MethodAware`, so a closure or an arrow function is never reported under a
  name of its own. Registering on `T_FUNCTION` alone reproduces that: the
  top-level closure and arrow function in `passing.php` each hold twelve
  decision points and neither tool says a word about them.
- **A closure's decision points belong to the declaration holding it.** PDepend
  walks a declaration's whole subtree, so an inline closure is scored against
  its host rather than measured separately. `mergesItsClosure()` in
  `failing.php` is 4 on its own and 11 with its closure, and both tools report
  it at 11. (#88's acceptance criteria expected closures to be scored
  independently; the measurement decided otherwise, and the sniff follows the
  tool.)
- **A nested named function is its own artifact.** `hostsNamedFunction()` in
  `passing.php` measures 2 while the `nested()` function inside it measures 9,
  and both tools report the two separately.
- **An anonymous class's constructor arguments are not part of its body.** They
  are ordinary expressions in the method that writes them. `makes()` in
  `passing.php` measures 3 — a ternary and an `&&` in an argument list — while
  the anonymous class's own `inner()` measures 3 in its own right.
- **A declaration with no body measures 1.** An abstract method and an
  interface method each score the base and nothing more, so neither tool can
  report either one.
- **Only the body is measured.** A ternary in a parameter default, a property
  default, or a constant default sits outside the body and counts for neither
  tool. `measure()` in `passing.php` holds one of each and measures 1.

## Divergences from PHPMD

One, measured and pinned by
`tests/fixtures/CyclomaticComplexitySniff/divergences.php`:

- **A method of an anonymous class is reported here and is invisible to PHPMD.**
  PDepend never surfaces those methods to a `MethodAware` rule, so a live PHPMD
  2.15.0 run over that fixture at `reportLevel` 1 reports only its outer
  `makes()` at 1 and says nothing about the anonymous class's `heavy()`,
  whatever its complexity. This sniff reports `heavy()` at 11. That is a gap in
  PHPMD rather than a decision — the method really does hold eleven paths, and
  reproducing the omission would mean writing code to suppress a true defect.
  The extra report keeps this sniff a superset, never looser.
  `CleanCode.Metrics.ExcessiveParameterList` and
  `CleanCode.Naming.ShortMethodName` record the same divergence for the same
  reason.

Everything else agrees. Beyond the fixtures, the sniff and `phpmd` were run
against real, non-fixture source already in this repository — three existing
sniff files, seventeen declarations spanning complexities 1 to 7 — and reported
the same declarations at the same counts. `tests/Standards/CyclomaticComplexityTest.php`
keeps one of those comparisons live as a test, so it cannot go stale.
