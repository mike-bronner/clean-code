# PHPMD Design: CouplingBetweenObjects

## Rule

- A class names fewer than 13 distinct other types.

**Why:** every type a class names is another thing that has to exist before the
class can run. A class with a dozen of them is hard to move, hard to test in
isolation, and hard to read, because understanding it means understanding all
of them.

```php
// PHPMD (and this ruleset) flags this:
class Foo
{
    private X $x;
    private Y $y;
    private Z $z;

    public function setFoo(\Foo $foo): void {}
    public function setBar(\Bar $bar): void {}
    // [... enough more distinct types to reach 13 ...]
}
```

**Configurable property:** `maximum`, default `13`.

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 1.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/CouplingBetweenObjects` | `CleanCode.Metrics.CouplingBetweenObjects` (message code `.Found`) |

No PHPCS, Slevomat, or Generic sniff counts the distinct types a class names.
The nearest candidates measure something else:
`SlevomatCodingStandard.Namespaces.UnusedUses` reads imports, but only to
reject the ones nothing uses; `SlevomatCodingStandard.Complexity.Cognitive`
scores one method's control flow; `Generic.Metrics.CyclomaticComplexity` and
`Generic.Metrics.NestingLevel` score control flow too; and
`SlevomatCodingStandard.Classes.ClassLength` counts lines. So this rule is a
custom sniff ([#114](https://github.com/mike-bronner/phpcs-rules/issues/114)),
registered automatically from `CleanCode/Sniffs/` when the standard loads;
running `phpcs` with it covers the rule and `phpmd` does not have to run
separately for it.

- **Detection** — the sniff registers on `T_CLASS` and `T_ANON_CLASS`, collects
  the types the class names, and reports once on the declaration line. The
  message quotes the count and the threshold, in PHPMD's own wording, so a
  reader moving off `phpmd` sees no change in the report.
- **`maximum` carries PHPMD's name and default (13)** — a consuming ruleset that
  already tunes PHPMD's rule can paste its value straight in:

  ```xml
  <rule ref="CleanCode.Metrics.CouplingBetweenObjects">
      <properties>
          <property name="maximum" value="8"/>
      </properties>
  </rule>
  ```

- **Error severity, not a warning** — the sniff raises errors out of the box, so
  a violation fails a `phpcs` run the way it fails a `phpmd` run. Left as a
  warning, `phpcs` would exit `0` and the mapping would not actually replace
  `phpmd`.
- **Not auto-fixable** — matching PHPMD. Cutting a class's dependencies means
  moving behaviour to another object; which behaviour, and to what, is a design
  decision with no mechanical rewrite.

## The threshold is inclusive

PHPMD reports a class whose coupling is **greater than or equal to** `maximum`,
not greater than it:

```php
$cbo = $node->getMetric('cbo');
$threshold = $this->getIntProperty('maximum');
if ($cbo >= $threshold) {
    $this->addViolation($node, array($node->getName(), $cbo, $threshold));
}
```

So exactly 13 dependencies is already a violation, and both phpmd.org's
"maximum number of acceptable dependencies" and #114's acceptance criteria
describe the advice rather than the test.
`tests/fixtures/CouplingBetweenObjectsSniff/boundaries.php` pins all three
points, and PHPMD 2.15.0 over that same file agrees exactly:

| Distinct dependencies | PHPMD 2.15.0 | This sniff |
| --- | --- | --- |
| 12 | silent | silent |
| 13 | reported | reported |
| 14 | reported | reported |

## What counts, and what does not

Measured against PHPMD 2.15.0 (PDepend 2.16.2) over
`tests/fixtures/CouplingBetweenObjectsSniff/`, both tools agree on all of these.
A type is counted once however often it is named, so the six spellings in
`DuplicateReferences` are one dependency between them.

| Shape | Counted? |
| --- | --- |
| Parameter type hints, including promoted constructor properties | yes |
| Property type hints | yes |
| Return type hints | yes |
| Each member of a union, intersection, or nullable type | yes, one each |
| `new Type()` | yes |
| `Type::method()`, `Type::CONSTANT`, `Type::class` | yes |
| `catch (Type $e)`, including each type of a multi-catch | yes |
| `$x instanceof Type` | yes |
| Parameters of a closure or arrow function inside the class | yes |
| `use` imports at the top of the file | yes — see the divergence below |
| Scalar and pseudo types (`int`, `string`, `array`, `void`, `never`, …) | no |
| The class's own name, `self`, `static`, `parent` | no |
| The `extends` and `implements` clauses | no |
| A `use` of a trait, and its adaptation block | no |
| An attribute, and a type named in its arguments | no |
| `use function` and `use const` imports | no |
| `new $class`, `$object::method()`, `$x instanceof $other` | no — no type is named |
| Traits, interfaces, enums, and plain functions | not checked at all |

Traits, interfaces, and enums are absent by design on both sides: PHPMD's rule
is declared `implements ClassAware`, and a live 2.15.0 run says nothing about
any of them. `passing.php` carries a 15-type trait, interface, enum, and plain
function to keep that pinned.

## Four divergences from PHPMD

Each was measured against a live PHPMD 2.15.0 run over the fixture named, and
each is a modelling gap in PDepend or a decision #114 makes explicitly, rather
than behaviour PHPMD's rule documents.

| Shape | PHPMD counts | This sniff counts | Fixture |
| --- | --- | --- | --- |
| An unused import | 0 | 1 | `imports.php` |
| A nested anonymous class's types | charged to the host | scored on the anonymous class | `divergences.php` |
| `mixed` and `object` | 1 each | 0 | `divergences.php` |
| `@var` / `@return` / `@throws` types | 1 each | 0 | `divergences.php` |

**The unused import** is the one divergence #114 asks for directly: it lists
`use` statements as a dependency source, and PDepend only ever sees a type that
is actually used. The gap cannot show up in code this ruleset passes, because
`CleanCode/ruleset.xml` also wires `SlevomatCodingStandard.Namespaces.UnusedUses`, which
makes an unused import an error in its own right. `imports.php` measures it:
PHPMD scores `ImportsAlone` at 0 and `ImportAndUsage` at 6, this sniff scores
both at 7.

**The anonymous class** rule is symmetrical, and it cuts both ways: each
class-like scope is counted on its own and never folded into the scope enclosing
it. A slim host holding a widely-coupled anonymous class is reported on the
anonymous class, not on the host. This is the treatment
`CleanCode.Metrics.ExcessivePublicCount` and `CleanCode.Metrics.TooManyFields`
already give it.

**`mixed` and `object`** are keywords, not types. PDepend does not list them
among its scalar types and so models them as classes, which makes
`function m(mixed $a, object $b)` two dependencies to PHPMD and none here.

**Docblock-only types** are the one place this sniff sees less than PHPMD, and
`CleanCode/ruleset.xml` closes it from the other side: it requires
`SlevomatCodingStandard.TypeHints.ParameterTypeHint`, `.ReturnTypeHint`, and
`.PropertyTypeHint`, so a type that exists only in an annotation is already an
error under this ruleset.

## Property hooks

PHP_CodeSniffer opens no scope for a PHP 8.4 property hook, so a variable
written inside one reports the class as its innermost condition, exactly as a
declared property does. That costs nothing: a variable that declares no property
has no type to read.

One shape is genuinely not counted — a `set` hook's parameter type
(`set (Incoming $incoming)`). Its list is not a function declaration, so PHPCS
does not surface it as a parameter. There is no PHPMD behaviour to match here:
PDepend cannot parse a file containing a hook at all.
`tests/fixtures/CouplingBetweenObjectsSniff/property-hooks.php` pins both halves.

## Fixtures

| Fixture | What it holds |
| --- | --- |
| `passing.php` | two 12-dependency scopes — a class and a nested anonymous class — plus a 15-type trait, interface, enum, and plain function, none of which is checked |
| `failing.php` | a class at exactly the threshold reached through every source, and a class at 18 through parameter types alone |
| `boundaries.php` | 12, 13, and 14 dependencies |
| `sources.php` | one class per counted source, each naming exactly one type, plus the near-miss shapes that must contribute nothing |
| `imports.php` | every `use` shape — plain, aliased, group, comma-separated, unused, `use function`, `use const` — and the short names they enable |
| `divergences.php` | the three shapes PDepend reads differently |
| `property-hooks.php` | a PHP 8.4 hooked property, whose hook bodies must cost nothing |
| `configured.php` | two small classes for exercising `maximum` |
| `unclosed-class.php` | an unterminated declaration, which leaves PHPCS with no scope to walk |
