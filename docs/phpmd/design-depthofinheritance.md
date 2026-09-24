# PHPMD Design: DepthOfInheritance

## Rule

- A class has fewer than 6 parents above it.

**Why:** a class reached through a long chain of parents is hard to read and
hard to change. Understanding what it does means reading every ancestor first,
and changing any one of them changes every class below it. A chain that deep is
usually a hierarchy modelling something it should have composed instead.

```php
// PHPMD (and this ruleset) flags this:
class A {}
class B extends A {}
class C extends B {}
class D extends C {}
class E extends D {}
class F extends E {}    // 5 parents — silent
class G extends F {}    // 6 parents — reported
```

**Configurable property:** `minimum`, default `6`.

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 0.2)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/DepthOfInheritance` | `CleanCode.Metrics.DepthOfInheritance` (message code `.TooDeep`) |

No PHPCS, Slevomat, or Generic sniff measures how far above a class its
inheritance chain reaches. The nearest candidates measure something else:
`SlevomatCodingStandard.Classes.ClassLength` counts lines,
`Generic.Metrics.CyclomaticComplexity` and `Generic.Metrics.NestingLevel` score
control flow, and nothing in either standard follows an `extends` link at all.
So this rule is a custom sniff
([#112](https://github.com/mike-bronner/clean-code/issues/112)), registered automatically
from `CleanCode/Sniffs/` when the standard loads; running `phpcs` with it covers
the rule and
`phpmd` does not have to run separately for it.

- **Detection** — the sniff registers on `T_CLASS`, walks the chain above the
  class, and reports once on the declaration. The message quotes the count and
  the threshold, so a reader moving off `phpmd` sees no change in the report.
- **`minimum` carries PHPMD's name and default (6)** — a consuming ruleset that
  already tunes PHPMD's rule can paste its value straight in:

  ```xml
  <rule ref="CleanCode.Metrics.DepthOfInheritance">
      <properties>
          <property name="minimum" value="4"/>
      </properties>
  </rule>
  ```

- **Detection only.** Flattening a hierarchy means redesigning it, which is a
  judgement call and not a mechanical rewrite. PHPMD offers no fix either.

## The threshold is inclusive, and `minimum` is a floor

PHPMD's rule class reads `maximum` first and falls back to `minimum`, and the
two use different comparisons:

```php
if (($comparison === 1 && $dit > $threshold) ||
    ($comparison === 2 && $dit >= $threshold)
) {
```

`$comparison` is 2 on the `minimum` path, which is the path the shipped ruleset
takes. A class with **exactly** 6 parents is therefore already a violation.

This matters because phpmd.org calls `minimum` the "maximum number of
acceptable parent classes", and [#112](https://github.com/mike-bronner/clean-code/issues/112)'s
acceptance criteria repeat that wording — "a class with exactly `minimum`
parent classes passes, one more fails". Both describe a strict `>`, which is
the opposite of what the tool does. A live PHPMD 2.15.0 run over
`tests/fixtures/DepthOfInheritanceSniff/boundaries.php` agrees with the code and
not with the prose, and the tool is what this package replaces. The AC's
boundary is corrected here rather than implemented as written.

`boundaries.php` pins the correction: 5 parents silent, 6 reported, 7 reported.

## An unseen parent weighs two

The metric is PDepend's `dit`, and PDepend does not simply count links:

```php
foreach ($class->getParentClasses() as $parent) {
    if (!$parent->isUserDefined()) {
        ++$dit;
    }
    ++$dit;
}
```

A parent PDepend never saw *declared* is not user-defined — it is a stub
synthesised from the name in the `extends` clause — so it adds 2 rather than 1,
and the walk stops there, because a stub has no parent of its own.

So `class Kid extends Vendor\Base {}` measures 2, not 1, and four in-project
ancestors above an unseen base already reach the threshold of 6. `failing.php`
carries that shape as `Grafted`, which is the discriminating case: it names 6
with only four resolved ancestors.

## The cross-file model, and its limits

This is the one rule in this package whose answer depends on the whole set of
files being analysed rather than on one file. PDepend resolves parents across
every file in the analysed set, which no per-file PHPCS sniff can do on its own.

The sniff therefore indexes the same set PHPCS itself is about to process —
`PHP_CodeSniffer\Files\FileList` over `$config->files`, the identical expansion,
with the same `--extensions` and the same ignore patterns — and resolves the
chain against that index.

The consequences, all of them deliberate:

- **It matches PHPMD's measured semantics.** PHPMD counts the parents inside the
  analysed fileset and no others. Run `phpcs src/` and a parent in `vendor/` is
  unseen, exactly as `phpmd src/` sees it.
- **It is order-independent.** The index is built from the file list, not
  accumulated as files are processed, so a child analysed before its parent
  measures the same as the reverse. Under `--parallel` each fork builds the same
  index from the same list, so the worker count cannot change a result.
- **A file always resolves against itself**, whether or not the file list holds
  it: its own declarations are consulted before the index. A single file passed
  on stdin behaves like `phpmd` given that one file — same-file ancestors
  resolve, everything above them is unseen.
- **The index is built at most once per run, and only when it is needed.** A
  class with no `extends` clause has depth 0 and returns before the index is
  ever touched, so a project without inheritance pays nothing for the rule.
- **Its limitation is the fileset boundary.** An ancestor excluded from the run
  — vendor code, an `<exclude-pattern>`, a narrower `phpcs` argument — is unseen
  and ends the walk at 2. That is a property of PHPMD's own model, reproduced
  rather than corrected.

Measured on a chain of eight classes:

| Fileset given to the tool | PHPMD | This sniff |
| --- | --- | --- |
| All eight classes in one file | flags the 6- and 7-parent classes | same |
| All eight in eight files, all analysed | flags the same two | same |
| Only the deepest file analysed | silent | silent |
| A class extending a never-declared class | silent at depth 2 | same |

The Composer autoloader is deliberately **not** consulted. It would resolve
vendor parents that PHPMD stays silent about, reporting depths PHPMD never
reports on ordinary framework code, and it is unavailable when PHPCS runs from a
global or PHAR install.

## Interpolated braces

The index reader tracks brace depth to tell a namespace-level `use` import from
a trait `use` inside a class body. PHP's lexer opens a `"{$expr}"` interpolation
on `T_CURLY_OPEN` and a `"${expr}"` one on `T_DOLLAR_OPEN_CURLY_BRACES`, in a
double-quoted string and in a heredoc alike, while the matching `}` arrives
bare. Counting only the bare braces would lose a level on every interpolated
string, close the enclosing namespace early, and misread the imports after it.

Those two are the whole of the asymmetry. Every other brace-bearing construct —
`$o->{$n}`, `${$n}`, `match`, an enum, a property hook, an attribute, a closure
— is bare on both sides, and a literal brace *inside* a string never reaches the
counter, because the lexer keeps it in the surrounding
`T_ENCAPSED_AND_WHITESPACE` or `T_CONSTANT_ENCAPSED_STRING`.

`interpolation.php` and `project/interpolated.php` pin one spelling each, so a
regression in either is reported on its own.

## What counts, and what does not

Measured against a live PHPMD 2.15.0 and pinned by fixtures:

- **Only `extends` counts.** An `implements` clause and a `use` of a trait
  contribute nothing to the count.
- **Only classes are reported.** The rule is `ClassAware`, so an interface
  hierarchy is silent however deep it runs, as are a trait and an enum. An
  anonymous class is not reported in its own right.
- **A cycle has no depth.** `class A extends B` with `class B extends A` — which
  PHP itself refuses to load — abandons the measurement rather than counting
  round the loop. PHPMD reports nothing here even with `minimum` lowered to 1,
  so silence is the faithful answer and not merely the safe one.
- **The report sits on the declaration's first modifier.** PDepend takes a
  class's start line from `abstract` or `final` when one is present, not from
  the `class` keyword, and not from an attribute group above it.

## Fixtures

`tests/fixtures/DepthOfInheritanceSniff/`:

| Fixture | What it pins |
| --- | --- |
| `passing.php` | A five-parent chain, a seven-deep interface hierarchy, and an anonymous class — all silent |
| `failing.php` | The three violating shapes, including `Grafted` and two declarations on one line |
| `boundaries.php` | The inclusive threshold: 5 silent, 6 reported, 7 reported |
| `cycle.php` | A cycle, silent even at `minimum` of 1 |
| `interpolation.php` | `{$expr}` in one unbraced namespace |
| `project/` | The cross-file chain — five parent spellings over five files, plus `${expr}` across a namespace boundary |

`tests/Standards/DepthOfInheritanceTest.php` asserts the exact lines, columns,
sources, and reported counts for each.
