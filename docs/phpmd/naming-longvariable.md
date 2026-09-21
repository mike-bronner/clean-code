# PHPMD Naming: LongVariable

## Rule

- A field, formal parameter, or local variable name must not be longer than 20
  characters, not counting the leading `$`.

**Why:** a name that long is usually carrying information that belongs
somewhere else — a type, a smaller scope, or a value object. It is a hint about
the surrounding design rather than a style preference.

```php
// PHPMD (and this ruleset) flags this:
class Something
{
    protected $reallyLongIntNameHere = -3;
}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 0.2)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/LongVariable` | `CleanCode.Naming.LongVariable` (message code `.TooLong`) |

No PHPCS, Generic, or Slevomat sniff measures a variable name's *length*. The
three `ValidVariableName` sniffs that ship with PHPCS (`PEAR`, `Zend`, and
`Squiz.NamingConventions.ValidVariableName`) all check casing and the
leading-underscore convention instead, and Slevomat's `Variables` sniffs
(`UnusedVariable`, `UselessVariable`) are about how a variable is used rather
than what it is called. So this rule is carried by a custom sniff, wired into
the master `CleanCode/ruleset.xml`
([#108](https://github.com/mike-bronner/phpcs-rules/issues/108)). Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **Detection** — fields, formal parameters, and local variables are all
  measured, whether the variable arrives as a property, a static property, a
  promoted constructor property, a parameter, an assignment, a `for` or
  `foreach` variable, a caught exception, a closure `use` variable, a
  destructured variable, a `global`, or a function-`static`.
- **The `$` is not counted.** PHPMD measures `ltrim($variableName, '$')`, so
  `$abc` is three characters. The report quotes the name *with* its `$`,
  because that is what the reader has to search for, and states the length
  *without* it.
- **Reported strictly above the threshold.** A name of exactly 20 characters
  passes; 21 fails. This matches PHPMD, which returns early on
  `$lengthWithoutDollarSign <= $threshold`.
- **One report per name per container**, at its first occurrence — not one per
  use. PHPMD keeps a `processedVariables` map and resets it for each node it
  visits. A class body de-duplicates its fields; a function de-duplicates its
  parameters together with every variable in its body, closures included,
  because PHPMD finds a closure's variables as children of the enclosing
  function. A field and a same-named local therefore sit in separate containers
  and are reported separately.
- **Member accesses are exempt.** A variable that is the object on the left of
  `->`, `?->`, or `::`, or the static field on the right of a `::`, is not
  reported: PHPMD skips any node under a `MemberPrimaryPrefix`. Array access is
  not a member access, so `$someLongArrayName['key']` is still measured. A
  comment between the variable and its operator makes no difference: PHPMD
  works from an AST, where a comment is trivia that cannot sit between the two,
  so the sniff steps over comments as well as whitespace when it looks for the
  operator.
- **Each construct is walked once.** A named class, trait, interface, enum, or
  function is an artifact of PDepend's wherever it is declared — nested inside
  a function body included — so it is walked in its own right and stepped over
  by the enclosing walk. PDepend builds no artifact for an **anonymous** class,
  so one is reached only through whatever encloses it: through the enclosing
  method or function when it sits in a body, and not at all at file scope,
  where PHPMD reports neither its fields nor the locals of its methods. That
  exception covers the anonymous class's own body and no deeper — a named
  function declared inside one of its methods, and a named class declared
  inside that function, are artifacts again, each with its own scope. That
  holds at file scope too: the anonymous class stays silent while the named
  function declared inside it is reported, in both tools.
  `tests/fixtures/LongVariableSniff/nesting.php` pins every one of those shapes
  against a live PHPMD run. (A class declared *directly* in any method body is
  a PHP parse error, which is why the nested-class case sits one level deeper.)
- **File scope is out of scope.** PHPMD's rule is `ClassAware`, `MethodAware`,
  `FunctionAware`, and `TraitAware`, and none of those covers code at file
  scope — including a closure declared there, which is not a named function. A
  long name outside every class, trait, interface, enum, and named function is
  silent in both tools.
- **Not auto-fixable** — matching PHPMD. Renaming a variable means rewriting
  every reference to it, and for a field every reference across the codebase,
  which a single-file, token-based fixer cannot do safely. The sniff reports
  errors with no fixer attached.
- **Error severity.** The sniff raises errors out of the box, so `phpcs` exits
  non-zero on a violation exactly as `phpmd` does, and no `<type>error</type>`
  override is needed in `CleanCode/ruleset.xml`.

## Configurable properties

The sniff carries PHPMD's three properties under PHPCS's camelCase spelling,
with PHPMD's defaults. `CleanCode/ruleset.xml` configures none of them, so the defaults
apply as shipped.

| PHPMD property | Sniff property | Default | Meaning |
| --- | --- | --- | --- |
| `maximum` | `maximum` | `20` | The reporting threshold. A name longer than this is flagged. |
| `subtract-prefixes` | `subtractPrefixes` | `''` | Comma-separated prefixes that do not count towards the length. |
| `subtract-suffixes` | `subtractSuffixes` | `''` | Comma-separated suffixes that do not count towards the length. |

```xml
<rule ref="CleanCode.Naming.LongVariable">
    <properties>
        <property name="maximum" value="30"/>
        <property name="subtractPrefixes" value="temporary"/>
        <property name="subtractSuffixes" value="Collection,Factory"/>
    </properties>
</rule>
```

The subtraction rules are PHPMD's, reproduced exactly rather than tidied up
(`PHPMD\Utility\Strings::lengthWithoutPrefixesAndSuffixes()`):

- **At most one suffix and at most one prefix** are subtracted, however many
  entries match.
- The entry subtracted is the **first one in the configured list** that matches,
  not the longest. With `subtractSuffixes="Collection,MockCollection"`, a name
  ending in `MockCollection` loses 10 characters, not 14.
- Both subtractions are taken from the length of the **original** name, so a
  prefix and a suffix that overlap are each subtracted in full.
- List entries are trimmed, and empty entries are dropped. That matters on the
  prefix side: an empty prefix matches *every* name (`strncmp($name, '', 0)` is
  `0`), so it would win the race, subtract nothing, and strand every real prefix
  behind it. An empty suffix is inert by comparison — `substr($name, -0)`
  returns the whole name, which never equals the empty string.
- Length is a **byte** count (`strlen`), not a character count, because PHPMD
  measures it that way. A multi-byte character therefore counts more than once
  in both tools.

## Divergences from PHPMD

Two, both deliberate, and both pinned by
`tests/fixtures/LongVariableSniff/divergences.php` so neither can drift
unnoticed.

**1. A trait's parameters and locals are reported once here, twice by PHPMD.**
PHPMD's `apply()` returns early after the fields of a `class` node, but a
`trait` node falls through to the general branch and walks the whole trait,
parameters and method bodies included. Every method is then walked again as a
node in its own right, so a trait's parameters and locals come back twice. A
trait's *fields* are reported once either way, and a class is unaffected.
Emitting the same violation twice helps nobody, so the duplicate is dropped
rather than reproduced.

**2. A name that only ever appears interpolated into a string is reported by
PHPMD and not here.** PHPCS hands a sniff the whole double-quoted string or
heredoc as a single token, so there is no variable token to find and no line to
report. This is a limitation of working from tokens rather than a choice, and it
is narrow in practice: a variable that is declared or assigned anywhere in the
same container is still reported at that occurrence, and a variable that is
*only* ever interpolated has no declaration in the file at all.

A **PHP 8.4 property hook** is a gap rather than a divergence: PHPMD 2.15.0
cannot parse a file containing one at all, failing with `Unexpected token: {`,
so there is nothing to match. A hooked property is measured like any other
field; the parameters and locals inside its hooks are not. PHP_CodeSniffer opens
no scope for a hook body, so those names would otherwise reach the field walk
looking class-scoped — and a hook local sharing a field's name would then
de-duplicate the field's own report away.
`tests/fixtures/LongVariableSniff/property-hooks.php` pins all three cases.

The ordering quirk below is **not** a divergence — it is reproduced:

- Whether an over-long name is reported at all can depend on what its *first*
  occurrence in the container happens to be. PHPMD marks a name as seen before
  it decides whether to exempt the occurrence (`checkNodeImage()` calls
  `addProcessed()` and only then `checkMaximumLength()`), so a name whose first
  occurrence is a member access is silenced for the whole container even where a
  later occurrence is a plain assignment.
  `tests/fixtures/LongVariableSniff/ordering.php` puts the same case both ways
  round; both tools report the second method only.

## Verification

Every fixture under `tests/fixtures/LongVariableSniff/` was run through a live
PHPMD 2.15.0 install as well as through this sniff, and the two agree line for
line everywhere except `divergences.php`, which exists to record where they do
not:

| Fixture | Properties | PHPMD and the sniff both report |
| --- | --- | --- |
| `passing.php` | defaults | nothing |
| `passing.php` | `maximum` 19 | lines 27, 32, 46, 48, 77, 88, 90 |
| `failing.php` | defaults | lines 16, 18, 24, 28, 30, 32, 36, 45, 49, 60, 62, 69, 71, 79, 86, 88, 94, 96 |
| `subtraction.php` | defaults | lines 22, 27, 34, 40, 48, 59 |
| `subtraction.php` | `temporary` / `Collection,Factory,MockCollection` | lines 48, 59 |
| `subtraction.php` | `temporary,temporaryWarehouseInventory` / — | lines 27, 34, 48, 59 |
| `subtraction.php` | `" , temporary , "` / — | lines 27, 34, 48, 59 |
| `ordering.php` | defaults | line 41 |

| Fixture | Properties | PHPMD reports | The sniff reports |
| --- | --- | --- | --- |
| `divergences.php` | defaults | lines 26, 28, 28, 30, 30, 51, 51 | lines 26, 28, 30 |
| `property-hooks.php` | defaults | nothing — it cannot parse the file | lines 28, 46 |

`tests/Standards/LongVariableTest.php` pins each of these. The `maximum` 19 row
carries more weight than it looks: the three file-scope names in `passing.php`
are 38 to 40 bytes, so they are over *both* thresholds, and they stay silent at
19 exactly as they did at 20 — which is only possible if they are excluded for
where they are rather than for how long they are. The last three
`subtraction.php` rows pin PHPMD's stop-at-the-first-match behaviour and its
list-trimming: were either loop to keep going past its first hit, or were the
empty entries kept, one of those rows would come out shorter.
