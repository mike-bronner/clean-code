# PHPMD CodeSize: TooManyMethods

## Rule

- A class declares no more than **25** methods, not counting accessors.

**Why:** a class with too many methods is doing too many things. The count is a
prompt to look for the smaller objects hiding inside it. Accessors are left out
because a wide accessor surface says nothing about how much the class does.

```php
// PHPMD (and this ruleset) flags this:
class Order
{
    public function place(): void {}
    // ... 25 more methods that are not accessors ...
}
```

_Source: [phpmd.org/rules/codesize.html](https://phpmd.org/rules/codesize.html)
(PHPMD CodeSize ruleset, since PHPMD 0.1; the default threshold was raised from
10 to 25 in PHPMD 2.3)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CodeSize/TooManyMethods` | `CleanCode.CodeSize.TooManyMethods` (message code `.MaxExceeded`) |

No PHPCS, Slevomat, or Squiz sniff counts methods per class. The nearest
neighbours measure something else entirely — `SlevomatCodingStandard.Classes
.ClassLength` and `SlevomatCodingStandard.Files.FileLength` count lines,
`SlevomatCodingStandard.Functions.FunctionLength` counts one function's lines,
and `Generic.Metrics.CyclomaticComplexity` scores branching within a single
method — so this rule is the custom
[`CleanCode.CodeSize.TooManyMethods`](../../CleanCode/Sniffs/CodeSize/TooManyMethodsSniff.php)
sniff, wired into the master ruleset (`CleanCode/ruleset.xml`) with PHPMD's default
thresholds ([#80](https://github.com/mike-bronner/clean-code/issues/80)).
Running `phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does
not have to run separately for it.

## Configurable properties

| Property | Default | Meaning |
| --- | --- | --- |
| `maxmethods` | `25` | The largest counted method total a class may declare |
| `ignorepattern` | `(^(set\|get\|is\|has\|with))i` | Methods whose name matches are left out of the count |

Both are set explicitly in `CleanCode/ruleset.xml`, so the thresholds a project runs under
are readable there rather than implied by the sniff's defaults:

```xml
<rule ref="CleanCode.CodeSize.TooManyMethods">
    <properties>
        <property name="maxmethods" value="25"/>
        <property name="ignorepattern" value="(^(set|get|is|has|with))i"/>
    </properties>
</rule>
```

## Behaviour

Verified against a live PHPMD 2.15.0 install, not against the rule page.

- **Classes only.** PHPMD's rule is `ClassAware`, so it never speaks about an
  interface, trait, or enum, and pdepend reports nothing for an anonymous
  class. The sniff registers `T_CLASS` alone, which reproduces that exactly:
  PHPCS gives interfaces, traits, enums, and anonymous classes their own
  tokens.
- **Every method counts, whatever its visibility.** PHPMD reads
  `getMethodNames()`, which covers public, protected, private, static, and
  abstract declarations, and the constructor.
- **Directly declared methods only.** Methods of a nested anonymous class, and
  functions declared inside a method body, belong to their own scope.
- **The threshold is exclusive.** PHPMD returns on `count <= maxmethods`, so a
  class holding exactly 25 counted methods is compliant and 26 is reported.
- **Reported on the class declaration.** The defect is the size of the whole
  class, so it has no statement line of its own.
- **Severity is error**, not the warning a metric might suggest. PHPMD fails a
  run on this violation; left as a warning, `phpcs` would exit `0` on an
  oversized class and the mapping would not actually replace `phpmd`.
- **Not auto-fixable** — matching PHPMD. Splitting a class is a decision about
  where each behaviour belongs, and no mechanical rewrite can make it.

### The ignore pattern matches prefixes, not accessors

`(^(set|get|is|has|with))i` is a case-insensitive prefix match with no word
boundary, so it excludes more than the accessors its purpose suggests:
`isolate`, `hash`, `within`, and `withdraw` are all ignored, as are `GETdata`
and `Setup`. That is PHPMD's own behaviour and the sniff reproduces it, so that
a class PHPMD passes is a class this ruleset passes. A project that wants a
narrower filter sets `ignorepattern` to one.

### Two documented divergences from phpmd.org

1. **The default `ignorepattern`.** The rule page still prints the pre-2.x
   `(^(set|get))i`. Every PHPMD 2.x `codesize.xml` ships
   `(^(set|get|is|has|with))i`, and the shipped value is what PHPMD actually
   runs. Following the page instead would count `is`/`has`/`with` accessors
   that PHPMD does not, and report classes PHPMD passes.
2. **A malformed `ignorepattern` is reported, not ignored.** PHPMD lets
   `preg_match()` fail on a broken pattern: it returns `false` for every method
   name, so no method is excluded and a typo in a ruleset quietly reads as a
   *stricter* rule than the one configured, under a scatter of raw PHP
   warnings. The sniff checks the pattern compiles first and reports
   `CleanCode.CodeSize.TooManyMethods.InvalidIgnorePattern` on the class
   instead, so a configuration defect does not arrive disguised as a code
   defect.
