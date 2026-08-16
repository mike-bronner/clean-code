# PHPMD Design: NumberOfChildren

## Rule

- A class has fewer than 15 direct subclasses.

**Why:** every subclass is another thing that changes when the parent changes.
A class with fifteen of them has stopped being an abstraction and become a
junction: the hierarchy is unbalanced, and the parent cannot be altered without
a survey of everything below it.

```php
// PHPMD (and this ruleset) flags this:
abstract class Base
{
}

class Child1 extends Base {}
class Child2 extends Base {}
// [... enough more direct children to reach 15 ...]
```

**Configurable property:** `minimum`, default `15`.

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 0.2)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/NumberOfChildren` | `CleanCode.Metrics.NumberOfChildren` (message code `.Found`) |

No PHPCS, Slevomat, or Generic sniff counts a class's subclasses, and none
could without leaving the file: PHP_CodeSniffer hands a sniff one file at a
time, and a parent's children are declared in files of their own. So this rule
is a custom sniff ([#110](https://github.com/mike-bronner/phpcs-rules/issues/110)),
wired in through `rules.xml`; running `phpcs` with that ruleset covers the rule
and `phpmd` does not have to run separately for it.

- **Detection** — the sniff registers on `T_CLASS`, resolves each declaration's
  fully qualified name, and reports once on the declaration line. The message
  quotes the count and the threshold in PHPMD's own wording, so a reader moving
  off `phpmd` sees no change in the report.
- **`minimum` carries PHPMD's name and default (15)** — a consuming ruleset that
  already tunes PHPMD's rule can paste its value straight in:

  ```xml
  <rule ref="CleanCode.Metrics.NumberOfChildren">
      <properties>
          <property name="minimum" value="8"/>
      </properties>
  </rule>
  ```

- **Detection only**, matching PHPMD. Rebalancing a hierarchy means moving
  behaviour between classes; there is no mechanical rewrite, and PHPMD offers no
  fix either.

## This is the ruleset's first cross-file rule

Every other sniff in this package answers from the file it is handed. This one
cannot, so it reads the whole run once and answers every file from the map it
builds.

"The whole run" is PHP_CodeSniffer's own file list —
`PHP_CodeSniffer\Files\FileList` over the paths `phpcs` was invoked with. Using
that rather than a directory walk of its own is what keeps the scanned set and
the linted set identical: the configured extensions, the ignore patterns, and
the ruleset's exclude-patterns all apply exactly once, to both.

The map is built on the first file that needs it and kept for the rest of the
run, so the cost is one extra read and tokenize per file in the run — once, not
once per file processed. Run in parallel, each worker process builds its own
copy.

Both halves of the answer — the child counts and the fully qualified name of
the class being reported on — come from the same `token_get_all()` pass.
Resolving the subject's name from PHP_CodeSniffer's token stream while the
counts came from PHP's own tokenizer would give two independent notions of "the
fully qualified name of this class", and any disagreement between them would
not produce a wrong count: it would produce a lookup that silently missed and a
sniff that quietly reported nothing.

A file the sniff cannot read contributes nothing rather than aborting the run.
A missing file can only lower a count, and a count that is too low stays
silent, where one that is too high accuses a class of a hierarchy it does not
have.

The scan reads files PHP has not agreed to compile, because `token_get_all()`
lexes rather than parses: `use A\{A\{A\{…` arrives intact however deep it goes
and however few of its braces close. Imports are therefore read with a loop and
not with recursion, so one crafted file cannot end the run for every file beside
it.

### Running phpcs on one file reports nothing, and that is parity

Point `phpcs` at a single file and the run contains one file, so only the
children declared *in* it are visible; a parent whose children live elsewhere
goes unreported. PHPMD does the same thing for the same reason. Measured
against a live PHPMD 2.15.0 install, on a `Base` with two children in its own
file and one in another:

| Invocation | PHPMD reports |
| --- | --- |
| `phpmd <directory>` (threshold 3) | `The class Base has 3 children.` |
| `phpmd Base.php` (threshold 3) | nothing |
| `phpmd Base.php` (threshold 2) | `The class Base has 2 children.` |

The metric is a property of the code under analysis, not of the class in the
abstract, in both tools. `tests/Standards/NumberOfChildrenTest.php` pins both
halves.

## The threshold is inclusive

PHPMD reports when the metric is *greater than or equal to* `minimum`:

```php
$nocc = $node->getMetric('nocc');
$threshold = $this->getIntProperty('minimum');
if ($nocc >= $threshold) {
    $this->addViolation($node, array(...));
}
```

So exactly 15 children is already a violation. phpmd.org's "maximum number of
acceptable child classes" reads like a strict `>`, and
[#110](https://github.com/mike-bronner/phpcs-rules/issues/110)'s acceptance
criteria go further and ask outright for a child count exactly at the threshold
to be silent — a live PHPMD 2.15.0 run disagrees with both, and the tool is
what this package replaces. `CleanCode.Metrics.CouplingBetweenObjects` (#114)
and `CleanCode.Functions.ExcessiveParameterList` (#95) read their own
thresholds inclusively for the same reason.

`tests/fixtures/NumberOfChildrenSniff/passing.php` carries fourteen children
and is silent; `failing.php` carries fifteen and is reported.

## What counts as a child

Every claim here was measured against a live PHPMD 2.15.0 (PDepend 2.16.2) run,
not read off phpmd.org, and each is pinned by a fixture:

- **`extends` only.** Sixteen classes implementing one interface report
  nothing, and an interface is not a subject in the first place — PHPMD's rule
  is `ClassAware`, so interfaces, traits, and enums are never examined.
- **Direct children only.** A grandchild counts toward its own parent, never
  toward the class above it.
- **Anonymous classes are not children.** `new class extends Base {}` leaves
  `Base`'s count untouched, in PHPMD and here.
- **Abstract classes are subjects.** An abstract parent is the normal shape for
  this smell, and PHPMD reports it.
- **Parents resolve fully qualified.** A child in another namespace reaching its
  parent through `use App\Base;`, through an alias, through a group import's
  `use App\{Base as Root};`, or by fully qualified name counts toward `App\Base`
  — never toward a second class called `Base` in another namespace. Names are
  compared lower-cased, because PHP class names are case-insensitive.
- **A trait `use` is never an import.** Only a `use` outside every class-like
  body binds a name, which the sniff tracks by brace depth. String
  interpolation — `{$expr}` and `${expr}` — is the one place PHP's tokenizer
  opens a brace without the plain `{` token every other opening brace carries,
  so both shapes are counted; `tests/fixtures/NumberOfChildrenSniff/interpolation/`
  pins each of them.
- **A short name is not unique within a file.** Braced `namespace` blocks let one
  file declare two different classes called `Foo`, even on one line, so a
  declaration is identified by the line it sits on and by the order it is written
  in — never by its name alone. `tests/fixtures/NumberOfChildrenSniff/namespaces/`
  pins both halves, each block's class carrying a different number of children so
  the report says which one it is about.

Piped input (`STDIN`) has no path and therefore no codebase to resolve children
against, so the sniff says nothing about it.
