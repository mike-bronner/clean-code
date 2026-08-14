# PHPMD CodeSize: TooManyFields

## Rule

- A class declares no more than **15** fields.

**Why:** a class carrying too many fields is usually holding several ideas at
once. The fix is to group the related ones into an object of their own — the
city/state/zip trio becoming an `Address`.

```php
// PHPMD (and this ruleset) flags this:
class Person
{
    private $one;
    private $two;
    // ... 14 more fields
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/TooManyFields` | `CleanCode.Metrics.TooManyFields` (message code `.MaxExceeded`) |

No bundled PHPCS, Slevomat, or Generic sniff counts a class's fields — the only
metric sniffs shipped are `Generic.Metrics.CyclomaticComplexity` and
`Generic.Metrics.NestingLevel` — so the rule is a custom sniff, wired into the
master ruleset (`rules.xml`) through the `./CleanCode/ruleset.xml` reference
([#98](https://github.com/mike-bronner/phpcs-rules/issues/98)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

- **Detection** — the sniff counts the fields a class declares itself and
  reports the class declaration line when that count goes *above* the
  threshold. A class sitting exactly on the threshold is silent, matching
  PHPMD, whose test is `vars <= maxfields`.
- **Reports errors** — the sniff raises an error rather than a warning, so a
  `phpcs` run fails on the violation the way a `phpmd` run does. The two Tier 1
  PHPMD mappings need a `<type>error</type>` override in `rules.xml` for this;
  a custom sniff simply reports the right severity itself.
- **Not auto-fixable** — matching PHPMD. Splitting a class means extracting a
  new object and re-pointing every use of the moved fields, which is a design
  decision rather than a mechanical rewrite.

## The threshold

`maxFields` (default `15`) is PHPMD's `maxfields` property, spelled the way
PHPCS spells properties. It is a sniff property, so a consuming project changes
it in its own ruleset:

```xml
<rule ref="CleanCode.Metrics.TooManyFields">
    <properties>
        <property name="maxFields" value="10"/>
    </properties>
</rule>
```

`rules.xml` does not set the property. The sniff already defaults to PHPMD's
15, and repeating the number there would only create a second place for it to
drift.

## What counts as a field

Everything PHPMD counts, measured against a live PHPMD 2.15.0 install:

- every declared property, whatever its visibility, and whether or not it is
  `static`, `readonly`, `var`, or preceded by an attribute;
- each variable of a multi-property declaration — `private $a, $b;` is two.

And, as in PHPMD, none of these:

- **class constants, interface constants, and enum cases** — a constant is not
  a field;
- **inherited properties** — they are counted against the class that declares
  them, so a child declaring 10 and a parent declaring 10 are two classes of 10,
  not one of 20;
- **properties reached through `use`** — they belong to the trait;
- **plain constructor parameters** — an argument is not a field;
- **local variables, closure parameters, and closure locals**.

Only classes are examined, as in PHPMD, whose rule is `ClassAware`. An interface
or enum cannot declare a property at all, and a trait's fields are counted where
they land: in whatever class uses the trait.

## Where this differs from PHPMD

Three shapes, all measured against PHPMD 2.15.0 rather than assumed, and all
pinned by fixtures so the gap cannot drift back into an unearned parity claim.
Every one of them is a field PHPMD *misses*, so this ruleset is stricter than
PHPMD here and never looser.

Two of the three trace to the same cause: PHPMD reads code through PDepend,
which models neither constructor property promotion nor PHP 8.4's newer
property modifiers.

| Shape | PHPMD 2.15.0 | This ruleset | Fixture |
| --- | --- | --- | --- |
| Promoted constructor properties | not fields; a fully promoted class reads as having none | counted | `divergences.php` |
| `final` properties and asymmetric visibility (`private(set)`) | unparsable; PDepend abandons the **whole file** and reports nothing in it | counted | `php84-modifiers.php` |
| A nested anonymous class | its fields are charged to the enclosing class, which is reported instead | the anonymous class is counted as the class it is, and reported itself | `divergences.php` |
| A top-level anonymous class | never examined | counted | `divergences.php` |
| PHP 8.4 property hooks | unparsable; PHPMD fails with `Unexpected token: {` | parsed; hook bodies are not fields | `property-hooks.php` |

**The promoted-property divergence is load-bearing, not a nicety.** This ruleset
requires promotion — `SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion`
is wired into `rules.xml` — so a compliant class keeps its fields in the
constructor signature. Copying PDepend's blind spot would leave the rule unable
to see the fields of the very classes it exists to police.

Fixtures live in `tests/fixtures/TooManyFieldsSniff/`, and
`tests/Standards/TooManyFieldsTest.php` pins the exact lines each one produces.
