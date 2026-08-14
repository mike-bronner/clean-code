# PHPMD CodeSize: TooManyPublicMethods

## Rule

- A class declares no more public methods than the configured threshold
  (`maxmethods`, default 10), counting only the methods whose names the ignore
  pattern does not match.

**Why:** a class with too many public methods carries too many
responsibilities. Split it into more fine-grained objects.

```php
// PHPMD (and this ruleset) flags this:
class Report {
    public function one() {}
    // ... nine more ...
    public function eleven() {}
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html#toomanypublicmethods)
(PHPMD CodeSize ruleset, since PHPMD 0.1)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/TooManyPublicMethods` | `CleanCode.Classes.TooManyPublicMethods` (message code `.Found`) |

No existing PHPCS or Slevomat sniff expresses this rule. The nearest candidates
were run against `tests/fixtures/TooManyPublicMethodsSniff/failing.php` and each
reported nothing on its five classes of eleven public methods:

- `SlevomatCodingStandard.Classes.ClassLength` — a class's *line* count, with no
  notion of visibility and no ignore pattern.
- `SlevomatCodingStandard.Classes.ForbiddenPublicProperty` — public
  *properties*, not methods, and a prohibition rather than a threshold.
- `Generic.Metrics.CyclomaticComplexity` — decision points inside one method.
- `Generic.Metrics.NestingLevel` — block depth inside one method.

So the rule is the custom `CleanCode.Classes.TooManyPublicMethods` sniff, wired
into the master ruleset (`rules.xml`) through `CleanCode/ruleset.xml`
([#83](https://github.com/mike-bronner/phpcs-rules/issues/83)). Running `phpcs`
with `rules.xml` therefore covers this rule, and `phpmd` does not have to run
separately for it.

- **Detection** — the sniff counts the public methods a class *declares
  itself*, drops the ones the ignore pattern matches, and reports when the
  remainder is **strictly greater** than `maxmethods`. A class holding exactly
  `maxmethods` public methods is silent.
- **Classes only** — PHPMD's rule implements PDepend's `ClassAware` interface
  and nothing else, so an interface, a trait, an enum, and an anonymous class
  are never handed to it. PHP_CodeSniffer tokenizes all four separately from
  `T_CLASS`, so registering `T_CLASS` alone reproduces that scope exactly.
- **Declared methods only** — an inherited method and a method imported from a
  trait are both invisible to PDepend's `ASTClass::getMethods()`, so neither
  tool resolves them and both stay single-file checks.
- **Innermost scope wins** — a named function declared inside a method body, and
  a method of an anonymous class returned from a method, belong to that inner
  scope. Neither counts towards the enclosing class, in either tool.
- **Visibility follows PHP** — a method declared without a modifier is public,
  and so is counted; `static` and `abstract` change nothing. `__construct`
  counts too: PHPMD's default pattern exempts accessor prefixes only.
- **Reported at the class** — one violation per class, on its `class` keyword,
  matching the line PHPMD reports.
- **Error severity** — the sniff reports errors, so an over-large class fails a
  `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed in
  `rules.xml`.
- **Not auto-fixable** — matching PHPMD. Splitting a class into finer-grained
  objects rewrites its call sites; that is a design change, not a mechanical
  rewrite.

## Configuration

Both properties are spelled exactly as PHPMD spells them and match the way PHPMD
matches, so an existing PHPMD configuration for this rule transfers verbatim:

```xml
<rule ref="CleanCode.Classes.TooManyPublicMethods">
    <properties>
        <property name="maxmethods" value="15"/>
        <property name="ignorepattern" value="(^(set|get))i"/>
    </properties>
</rule>
```

- `maxmethods` — the count above which a class is reported. Defaults to `10`,
  exactly as PHPMD's `codesize.xml` ships it.
- `ignorepattern` — a PCRE. A method whose name matches it is left out of the
  count. A pattern PHP cannot compile exempts nothing, so a configuration
  mistake over-reports rather than switching the rule off silently — the same
  call PHPMD makes.

### The `ignorepattern` default, and phpmd.org

The default here is `(^(set|get|is|has|with))i`, copied verbatim — bracket
delimiters included — from the `<property name="ignorepattern">` element in
**PHPMD 2.15.0's own `src/main/resources/rulesets/codesize.xml`**.

[#83](https://github.com/mike-bronner/phpcs-rules/issues/83) quotes
`(^(set|get))i` instead, taken from phpmd.org's rule page, which still documents
the older value. The two disagree, and the shipped ruleset is what PHPMD
actually runs: verified by running PHPMD 2.15.0 over a class of eleven public
methods named `is1()`…`is11()`, which it leaves alone under its own defaults and
reports the moment `ignorepattern` is set to `(^(set|get))i`.

Tracking the shipped value is what makes an unconfigured `phpcs` run and an
unconfigured `phpmd` run agree, which is the whole point of the mapping — the
documented value would report classes PHPMD does not, so the two tools would
disagree out of the box and a project could not retire `phpmd` on this rule
without re-triaging its findings. A project that wants the narrower documented
behaviour sets the property explicitly, as in the snippet above; that path is
pinned by `tests/Standards/TooManyPublicMethodsTest.php`.

### Empty property values

PHP_CodeSniffer turns an empty `<property>` value into `null` before assigning
it, so both properties are nullable and both fall back safely:

- `<property name="maxmethods" value=""/>` — falls back to PHPMD's default of
  10. Treating it as "no threshold" would switch the rule off silently, and a
  bare `(int)` cast would make it 0 and report every class in the codebase.
- `<property name="ignorepattern" value=""/>` — exempts nothing, so every public
  method counts.

Only the second diverges from PHPMD, and only in the reporting-more direction:
an empty value in PHPMD's own XML leaves its default pattern in place, so PHPMD
would still exempt the accessors. Reporting more than PHPMD under a deliberately
chosen setting keeps `phpmd` out of the pipeline; the reverse would not. Both
paths are pinned by tests, because a non-nullable declaration turns either empty
value into a fatal `TypeError` that aborts the whole ruleset parse.

## Divergences from PHPMD

**None under either tool's defaults.** Every claim above was checked against a
live PHPMD 2.15.0 run over the fixtures named, and the two tools report the same
classes at the same counts on the same lines:

| Fixture | PHPMD 2.15.0 | This ruleset |
| --- | --- | --- |
| `passing.php` — the boundary, non-public methods, the five ignore-pattern prefixes, an interface / trait / enum / anonymous class of 15 methods each, nested declarations, inherited and trait-imported methods | silent | silent |
| `failing.php:13` — 11 plain public methods | 11 reported | 11 reported |
| `failing.php:32` — `__construct` plus 10 | 11 reported | 11 reported |
| `failing.php:51` — 5 public static, 6 public abstract | 11 reported | 11 reported |
| `failing.php:69` — 11 methods with no visibility modifier | 11 reported | 11 reported |
| `failing.php:88` — 16 public methods, 5 exempted by the default pattern | 11 reported | 11 reported |
| `configured.php` with `maxmethods=3` | 3 classes, at 11 / 10 / 4 | 3 classes, at 11 / 10 / 4 |
| `configured.php` with `ignorepattern=(^(set|get))i` | 2 classes, at 11 / 12 | 2 classes, at 11 / 12 |

The one behaviour the two tools do not share is an *explicitly emptied*
`ignorepattern`, described above: PHPMD's XML cannot express it, and here it
means "exempt nothing". That direction reports more, never less, so no PHPMD
finding is lost.
