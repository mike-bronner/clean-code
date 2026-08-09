# PHPMD CodeSize: ExcessiveClassComplexity

## Rule

- A class's Weighted Method Count (WMC) — the sum of the cyclomatic complexity
  of every method it declares — stays below a configured maximum.

**Why:** WMC estimates how much time and effort it takes to modify and maintain
a class. A large number means the class does too much, and a class with many
complex methods has a greater potential impact on everything derived from it.

```php
// PHPMD (and this ruleset) flags this once the methods' complexities add up:
class Foo {
    public function bar() {
        if ($a == $b)  {
            if ($a1 == $b1) {
                fiddle();
            } elseif ($a2 == $b2) {
                fiddle();
            }
        }
    }
    public function baz() {
        // ... and forty more branches across the rest of the class
    }
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html#excessiveclasscomplexity)
(PHPMD CodeSize ruleset, since PHPMD 0.2.5)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/ExcessiveClassComplexity` | `CleanCode.Metrics.ExcessiveClassComplexity` (message code `.MaximumExceeded`) |

No existing PHPCS or Slevomat sniff expresses this rule. The measurement is a
whole-class total, and every candidate measures one function at a time, so none
of them can reach it — `Generic.Metrics.CyclomaticComplexity` and
`Generic.Metrics.NestingLevel` both register on `T_FUNCTION` alone, and
`SlevomatCodingStandard.Complexity.Cognitive` registers on `T_FUNCTION` and
`T_CLOSURE` and reports a different metric (cognitive complexity) besides.

Run against this rule's own fixtures, the difference is not a matter of degree:

- On `tests/fixtures/ExcessiveClassComplexitySniff/failing.php`,
  `Generic.Metrics.CyclomaticComplexity` reports the two `varied()` methods and
  nothing else. It never mentions either class, and it says exactly the same
  thing about `AtExactlyTheMaximum` (WMC 50, over the threshold) as about
  `AtOneBelowTheMaximum` in `passing.php` (WMC 49, under it) — the whole
  difference between those two classes lives in boolean operators, which that
  sniff does not count.
- On `passing.php` it reports `uncounted()` at a complexity of 52, a method
  PDepend scores 1. So it does not merely miss the class total; it disagrees
  about the method totals it is summing.
- `Generic.Metrics.NestingLevel` reports nothing at all on either fixture.
- `SlevomatCodingStandard.Complexity.Cognitive` scores `varied()` at 17 where
  PDepend scores it 13.

So the rule is the custom `CleanCode.Metrics.ExcessiveClassComplexity` sniff,
wired into the master ruleset (`rules.xml`) through `CleanCode/ruleset.xml`
([#87](https://github.com/mike-bronner/phpcs-rules/issues/87)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

- **Error severity** — the sniff reports errors, so an over-complex class fails
  a `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed
  in `rules.xml`.
- **Not auto-fixable** — matching PHPMD. Splitting a class up moves methods
  between files and rewrites every call site; that is a design change, not a
  mechanical rewrite.
- **The report is attached to the class declaration**, because the measurement
  describes the whole class rather than any one line inside it.

## Configuration

The property is spelled exactly as PHPMD spells it and defaults to the value
PHPMD's `codesize.xml` ships, so an existing PHPMD configuration for this rule
transfers verbatim:

```xml
<rule ref="CleanCode.Metrics.ExcessiveClassComplexity">
    <properties>
        <property name="maximum" value="40"/>
    </properties>
</rule>
```

- `maximum` — the weighted method count at which a class is reported. Defaults
  to `50`.

A value PHP reads as no number at all (`"forty"`) casts to `0`, which reports
every class rather than switching the rule off: a configuration mistake
over-reports rather than going quiet, the same call this ruleset records for
`DisallowBooleanArgumentFlag`'s `ignorepattern`.

## At or above the maximum, not above it

**PHPMD reports a class whose WMC is at or above `maximum`, not strictly above
it.** Its `Rule\Design\WeightedMethodCount` — the class PHPMD's
`codesize.xml` registers under the name `ExcessiveClassComplexity` — compares
`$actual >= $threshold`. At the default of 50 a class measuring exactly 50 is a
violation, and one measuring 49 is not.

This contradicts the rule's own property description ("The maximum WMC tolerable
for a class"), so it is worth stating plainly. The sniff matches PHPMD's
behaviour rather than its wording, because reading `maximum` as a tolerated
value would leave this ruleset silent on a class PHPMD reports — and being
looser than PHPMD at any count is the one thing this mapping must not be.

`tests/fixtures/ExcessiveClassComplexitySniff/passing.php` (49, silent) and
`failing.php` (50, reported) pin the boundary from both sides, and
`configured.php` pins the same comparison at a configured maximum of 13.

## What counts towards the total

Each method a class declares contributes 1, plus 1 for each decision point in
its body. Every entry below was measured against a live PHPMD 2.15.0 / PDepend
run, not read off the documentation.

| Construct | Worth |
| --- | --- |
| every method the class declares, including an abstract one | 1 each |
| `if`, `elseif` (`else if` too — it is `else` followed by `if`) | 1 each |
| `while`, `for`, `foreach`, and a `do … while` loop | 1 each |
| `case` in a `switch` | 1 each |
| `catch`, however many exception types it lists | 1 each |
| `&&`, `\|\|`, `and`, `or` | 1 each |
| `?:` — both the full ternary and the short form | 1 each |
| `else`, `default`, `finally`, `xor`, `??`, `??=`, `?->`, `goto` | 0 |
| `match` and its arms | 0 |

`match` scoring nothing is PDepend's behaviour, not an omission here: PDepend
2.x has no visitor for a match expression, so a twenty-arm `match` adds nothing
to the total in either tool.

## What the measurement covers

Also all verified against the live tools:

- **Named classes only.** PHPMD's rule is `ClassAware`, so an interface, a
  trait, an enum, and an anonymous class are never reported however complex
  they are. `tests/fixtures/ExcessiveClassComplexitySniff/not-a-class.php`
  carries one of each, every one of them past the maximum, and the sniff stays
  silent on all of them.
- **Only the methods the class itself declares.** Methods reached through
  `use SomeTrait;` score against the trait: PDepend gives the using class 0, and
  so does the sniff.
- **A nested declaration owns its own complexity.** A named function or a
  class-like declared inside a method is a separate artifact, so its body never
  reaches the enclosing class's total.
- **An anonymous class's constructor arguments are not part of that body.**
  They are ordinary expressions in the method that writes them, so PHP
  evaluates them there and PDepend scores them there — only the anonymous
  class's body is skipped. `AnonymousClassArguments` in
  `tests/fixtures/ExcessiveClassComplexitySniff/passing.php` measures 5 in both
  tools: a ternary and an `&&` in an argument list, plus a second method
  proving the same holds for an anonymous class nested in another one's
  arguments. Skipping from the `class` keyword instead of from the opening
  brace drops it to 2.
- **A closure or arrow function does not.** Its decision points belong to the
  method it is written in, which is what PDepend does by walking the method's
  whole subtree. `InlineFunctionBodies` in
  `tests/fixtures/ExcessiveClassComplexitySniff/passing.php` holds one of each
  and measures 3 in both tools — 2 if either one were skipped over, 1 if both
  were.
- **Only method bodies are measured.** A ternary or boolean operator in a
  parameter default, a property default, or a constant default is outside every
  method body and counts for neither tool. `MemberDefaults` in the same fixture
  holds one of each and measures 3 in both tools — the value of its two methods
  alone.

## Divergences from PHPMD

None found. The sniff was calibrated by measuring 45 classes covering every
construct above with both PDepend's `wmc` metric and the sniff, and the two
agree on every one; running PHPMD 2.15.0 over this rule's four fixtures at the
default maximum reports `AtExactlyTheMaximum` and `WellAboveTheMaximum` and
nothing else, which is exactly what the sniff reports.

Two shapes are worth naming as *deliberately* matched rather than merely
untested, because a reasonable implementation would get them wrong:

- A class measuring exactly `maximum` is reported (see above).
- `match`, `??`, and `xor` score nothing, so a class built entirely from them
  stays silent no matter how many there are.
