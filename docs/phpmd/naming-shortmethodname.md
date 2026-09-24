# PHPMD Naming: ShortMethodName

## Rule

- Do not give a function or method a name shorter than `minimum` characters.

**Why:** a one- or two-letter name carries no information. The reader has to
open the body to learn what it does, at every call site.

```php
// PHPMD (and this ruleset) flags this:
class ShortMethod
{
    public function a($index) // violation
    {
    }
}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 0.2)_

### Configurable properties

| Property | Default | Meaning |
| --- | --- | --- |
| `minimum` | `3` | Shortest acceptable name. A name of exactly this length passes. |
| `exceptions` | `""` | Comma-separated names that are never reported, however short. |

Both are PHPMD's own names, defaults and semantics, so a `phpmd.xml`
configuration transfers unchanged:

```xml
<rule ref="CleanCode.Naming.ShortMethodName">
    <properties>
        <property name="minimum" value="4"/>
        <property name="exceptions" value="id,to"/>
    </properties>
</rule>
```

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/ShortMethodName` | `CleanCode.Naming.ShortMethodName` (message code `.TooShort`) |

No existing PHPCS or Slevomat sniff measures declaration-name *length*. None
of the twelve `NamingConventions` sniffs `squizlabs/php_codesniffer` ships
looks at how long a name is: they judge casing
(`Generic.NamingConventions.CamelCapsFunctionName`,
`PEAR`/`Squiz.NamingConventions.ValidFunctionName`), affixes
(`AbstractClassNamePrefix`, `InterfaceNameSuffix`, `TraitNameSuffix`), or
whether a method name matches its class (`ConstructorName`). And
`slevomat/coding-standard` ships no name-length rule either — the only
`strlen()` calls in its sniffs measure line and signature length. So this is a
custom sniff
([#111](https://github.com/mike-bronner/clean-code/issues/111)), registered
automatically from `CleanCode/Sniffs/` when the standard loads. Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **Detection** — the sniff listens for `T_FUNCTION`, which PHPCS assigns to
  named functions and methods only. That is exactly the pair PHPMD's rule
  class covers, since it implements both `MethodAware` and `FunctionAware`.
  Methods on classes, interfaces, abstract classes, traits and enums all
  count, as do global functions and functions declared inside a method body.
- **The boundary is inclusive** — PHPMD compares `strlen($name) >= $threshold`,
  so at the default a three-character name passes and a two-character one
  fails.
- **Byte length, not character length** — PHPMD calls `strlen()`. A
  two-letter name written with a multi-byte letter (`añ`) measures three bytes
  and passes in both tools. Using `mb_strlen()` here would report names PHPMD
  accepts.
- **`exceptions` is not trimmed** — PHPMD explodes the raw property on commas
  and compares strictly, so `"a, b"` exempts `a` and `" b"`, never `b`. This
  sniff reproduces that rather than being kinder: trimming would exempt names
  `phpmd` still reports, and a ruleset that exists to replace `phpmd` must not
  fall silent where `phpmd` speaks. The comparison is case-sensitive for the
  same reason, even though PHP method names are not.
- **No magic-method carve-out** — because PHPMD has none. It needs none at the
  default threshold, since the shortest magic method (`__get`) is five
  characters; raise `minimum` past five and both tools report `__get`,
  `__set`, `__call` and `__construct` alike.
- **Unnamed declarations are never reported** — PHPCS gives closures
  `T_CLOSURE` and arrow functions `T_FN`, so neither reaches the sniff. PHPMD
  has no name to measure on them either.
- **A declaration with no parameter list is not reported** — PHPCS emits
  `T_FUNCTION` for unparseable input but sets no `parenthesis_opener`, leaving
  nothing to prove which token is the name. The sniff refuses to guess.
- **Not auto-fixable** — matching PHPMD. Renaming a function means updating
  every call site, every interface it satisfies and anything reaching it by
  string, so there is no safe mechanical rewrite.
- **Severity** — the sniff reports errors directly, so unlike the Tier 1
  mappings there is no built-in warning to raise: `phpcs` already exits
  non-zero on a violation, the way `phpmd` does.

## Divergence from PHPMD

One shape is reported here and not by `phpmd`: **a method of an anonymous
class**.

```php
$logger = new class {
    public function zz() {} // phpcs reports; phpmd does not
};
```

`pdepend`, which builds the tree `phpmd` walks, produces no method node for
the body of `new class { … }`, so a `MethodAware` rule is never handed one and
stays silent at any threshold. Verified against a live `phpmd` 2.15 run from a
cold `pdepend` cache.

The extra report is kept. This ruleset exists so `phpmd` does not have to run,
and reporting more than `phpmd` never leaves a real violation unreported —
falling silent where `phpmd` speaks would. The name really is below the
minimum, so it is a true positive, not a false one.

A function declared *inside* a method body looks like the same kind of nesting
but is **not** a divergence: `pdepend` does collect it, and both tools report
it.

Both behaviours are pinned by
`tests/fixtures/ShortMethodNameSniff/divergences.php` and
`tests/fixtures/ShortMethodNameSniff/failing.php`, whose every reported line
was cross-checked against that same live `phpmd` run.
