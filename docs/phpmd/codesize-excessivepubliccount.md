# PHPMD CodeSize: ExcessivePublicCount

## Rule

- A class or trait declares fewer than 45 public methods and attributes
  combined.

**Why:** a large public surface is a large contract. Every public member is
another entry point to test and another thing a caller can depend on, so a type
with dozens of them is usually several types wearing one name.

```php
// PHPMD (and this ruleset) flags this:
class Foo
{
    public $value;
    public $something;
    // [... 43 more public attributes and methods ...]
}
```

**Configurable property:** `minimum`, default `45`.

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/ExcessivePublicCount` | `CleanCode.Metrics.ExcessivePublicCount` (message code `.Found`) |

No PHPCS, Slevomat, or Generic sniff counts a type's public surface. The
nearest candidates measure something else entirely:
`Generic.Metrics.CyclomaticComplexity` and `Generic.Metrics.NestingLevel` score
control flow, `SlevomatCodingStandard.Classes.ClassLength` counts lines, and
`SlevomatCodingStandard.Complexity.Cognitive` scores one method at a time. So
this rule is a custom sniff
([#96](https://github.com/mike-bronner/phpcs-rules/issues/96)), registered
automatically from `CleanCode/Sniffs/` when the standard loads; running `phpcs`
with it covers the rule and `phpmd` does not have to run separately for it.

- **Detection** — the sniff registers on `T_CLASS`, `T_ANON_CLASS`, and
  `T_TRAIT`, counts the public methods and public properties the type declares
  itself, and reports once on the declaration line. The message quotes the
  count and the threshold, so the reader never re-counts by hand.
- **`minimum` carries PHPMD's name and default (45)** — a consuming ruleset that
  already tunes PHPMD's rule can paste its value straight in:

  ```xml
  <rule ref="CleanCode.Metrics.ExcessivePublicCount">
      <properties>
          <property name="minimum" value="30"/>
      </properties>
  </rule>
  ```

- **Error severity, not a warning** — the sniff raises errors out of the box, so
  a violation fails a `phpcs` run the way it fails a `phpmd` run. Left as a
  warning, `phpcs` would exit `0` and the mapping would not actually replace
  `phpmd`.
- **Not auto-fixable** — matching PHPMD. Splitting an oversized type is a design
  change; which members move, and to what, is not machine-derivable.

## The threshold is inclusive

PHPMD reports a type whose public count is **greater than or equal to**
`minimum`, not greater than it. Its rule class returns early only on the
strictly-below case:

```php
$threshold = $this->getIntProperty('minimum');
$cis = $node->getMetric('cis');
if ($cis < $threshold) {
    return;
}
```

So exactly 45 public members is already a violation, and the message's "less
than 45" is the advice, not the test. `tests/fixtures/ExcessivePublicCountSniff/boundaries.php`
pins all three points, and PHPMD 2.15.0 over that same file agrees exactly:

| Public members | PHPMD 2.15.0 | This sniff |
| --- | --- | --- |
| 44 | silent | silent |
| 45 | reported | reported |
| 46 | reported | reported |

## What counts, and what does not

Measured against PHPMD 2.15.0 (PDepend 2.16.2) over
`tests/fixtures/ExcessivePublicCountSniff/`, both tools agree on all of these:

| Shape | Counted? |
| --- | --- |
| Public methods, explicit or implicit (`function m() {}`) | yes |
| Public properties, including `var $x;` and `public static $x;` | yes |
| Each name in a multi-property declaration (`public $a, $b;`) | yes, one each |
| Public abstract methods | yes |
| `protected` / `private` members | no |
| Constants, including `public const` | no — a constant is not an attribute |
| A method's local variables | no |
| Interfaces, however wide | not checked at all |
| Enums, however wide | not checked at all |

Interfaces and enums are absent by design on both sides. PHPMD's rule is
declared `implements ClassAware, TraitAware`, and PDepend's `ClassLevelAnalyzer`
leaves `visitInterface()` empty with the comment *"we don't want interface
metrics"*. A 50-method interface and a 50-method enum sit in `passing.php` to
keep that pinned.

## Stricter than PHPMD on three shapes

PHPMD measures through PDepend's `cis` metric, and PDepend does not model three
constructs that plainly are public surface. Running PHPMD 2.15.0 over
`tests/fixtures/ExcessivePublicCountSniff/divergences.php` produces **no**
`ExcessivePublicCount` violation on any of them; this sniff reports all three.
Each is a modelling gap rather than a decision PHPMD's rule documents, and each
hides a genuinely excessive public surface, so all three are kept.

| Shape | PHPMD counts | This sniff counts |
| --- | --- | --- |
| A trait's public properties | 0 — PDepend counts a trait's methods only | one each |
| A constructor's promoted public properties | 0 — PDepend does not model promotion | one each |
| An anonymous class's own members | not reported | counted as its own scope |

The promoted-property gap is the one that matters most here. `CleanCode/ruleset.xml`
requires `SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion`,
so promotion is this standard's mandated way to declare a public property.
Deferring to PDepend would leave the rule blind to the exact shape the ruleset
itself demands.

The anonymous-class rule is symmetrical, and it cuts both ways: each class-like
scope is counted on its own and never folded into the scope enclosing it. A slim
host holding a wide anonymous class is reported on the anonymous class, not on
the host — `passing.php` carries the under-threshold version of that shape, and
`divergences.php` the over-threshold one.

## Fixtures

| Fixture | What it holds |
| --- | --- |
| `passing.php` | a 44-member class and trait, plus every near-miss shape: a wide interface, a wide enum, 50 constants, 100 non-public members, 50 method locals, and a narrow nested anonymous class |
| `failing.php` | a class at the threshold, a class reaching it through mixed methods and properties, and a trait at the threshold |
| `boundaries.php` | 44, 45, and 46 public members |
| `divergences.php` | the three shapes PDepend cannot see |
| `configured.php` | two small types for exercising `minimum` |
| `unclosed-class.php` | an unterminated declaration, which leaves PHPCS with no scope to walk |
