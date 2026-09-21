# PHPMD Naming: ShortVariable

## Rule

- Do not give a property, parameter or local variable a name shorter than
  `minimum` characters.

**Why:** a one- or two-letter name carries no information. The reader has to
find the assignment to learn what the value is, at every use.

```php
// PHPMD (and this ruleset) flags this:
class Something
{
    private $q = 15;            // violation — field

    public function main(array $as): int  // violation — parameter
    {
        $r = 20;                // violation — local

        for ($i = 0; $i < 10; $i++) {   // not a violation — for-loop counter
            $r += $i;
        }

        return $r;
    }
}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 0.2)_

### Configurable properties

| Property | Default | Meaning |
| --- | --- | --- |
| `minimum` | `3` | Shortest acceptable name, counted without the `$`. A name of exactly this length passes. |
| `exceptions` | `""` | Comma-separated names, written without the `$`, that are never reported however short. |

Both are PHPMD's own names, defaults and semantics, so a `phpmd.xml`
configuration transfers unchanged:

```xml
<rule ref="CleanCode.Naming.ShortVariable">
    <properties>
        <property name="minimum" value="4"/>
        <property name="exceptions" value="id,to"/>
    </properties>
</rule>
```

PHPMD reads a third property, `allow-short-variables-in-loop`, that its own
`rulesets/naming.xml` does not declare. It is not carried over: this sniff
behaves as PHPMD does at that property's default of `true`, exempting a
`foreach`'s key and value variables.

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/ShortVariable` | `CleanCode.Naming.ShortVariable` (message code `.TooShort`) |

No existing PHPCS or Slevomat sniff measures variable-name *length*.
`Squiz.NamingConventions.ValidVariableName`, which `CleanCode/ruleset.xml` already wires
in for the casing conventions, judges casing and underscores;
`VariableAnalysis.CodeAnalysis.VariableAnalysis` judges whether a variable is
defined and used; and `slevomat/coding-standard` ships no name-length rule at
all. So this is a custom sniff
([#106](https://github.com/mike-bronner/phpcs-rules/issues/106)), registered
automatically from `CleanCode/Sniffs/` when the standard loads. Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **What counts as a variable** — a property (PHPMD's `ClassAware` and
  `TraitAware` halves), a parameter of a function, method, closure or arrow
  function, a promoted constructor parameter, a local, a `static` or `global`
  declaration, a destructured variable, and a name interpolated into a
  double-quoted string or heredoc. A nowdoc and a single-quoted string
  interpolate nothing, so the short names they spell are text.
- **One report per name per scope** — PHPMD keeps a per-node map of names it
  has already looked at, so a local used ten times is reported once, at its
  first occurrence. A class-like carries one scope for its properties, every
  named function or method one for its parameters and body, and everything
  outside both shares the file's scope. Closures and arrow functions fold into
  the scope around them, exactly as pdepend nests them under the method that
  declares them.
- **The first occurrence decides** — a name whose first occurrence is exempt
  is never reported, even where a later occurrence would be. PHPMD marks the
  name processed before it consults the context, and this reproduces that.
- **The boundary is inclusive** — PHPMD compares `strlen($name) >= $threshold`
  on the name without its `$`, so at the default a three-character name passes
  and a two-character one fails.
- **Byte length, not character length** — PHPMD calls `strlen()`. `$añ` is two
  characters but three bytes, so both tools accept it at the default. Using
  `mb_strlen()` here would report names PHPMD accepts.
- **Exempt contexts** — PHPMD's `isNameAllowedInContext()` allows a short name
  in the init section of a `for` header, as the variable a `catch` binds, and
  as a `foreach`'s key or value. A **by-reference** `foreach` value is *not*
  exempt in either tool (PHPMD matches the loop variable by image against the
  `foreach` node's own children, and the reference expression is not one of
  them), and neither is a variable destructured out of the value.
- **`exceptions` is not trimmed, and carries no `$`** — PHPMD strips the sigil
  from the name, then explodes the raw property on commas without trimming the
  parts, so `"ab, cd"` exempts `ab` and `" cd"`, never `cd`, and an entry
  written `$ab` exempts nothing. This sniff reproduces that rather than being
  kinder: trimming would exempt names `phpmd` still reports, and a ruleset that
  exists to replace `phpmd` must not fall silent where `phpmd` speaks. The
  comparison is case-sensitive for the same reason.
- **An unusable `minimum` falls back to 3** — `(int) null` and `(int) 'abc'`
  are both `0`, and a threshold of `0` passes every name, so casting a typo'd
  ruleset value would switch the rule off silently. It falls back to PHPMD's
  default instead and keeps enforcing.
- **A property access is not a variable** — neither `$this->ab` nor
  `self::$ab` is measured, in either tool, even though PHPCS spells the second
  one with a variable token. The declaration is where the name is fixed, and
  that is where the report lands.
- **A declaration with no parameter list is not read** — PHPCS emits
  `T_FUNCTION` for unparseable input but sets no `parenthesis_opener`, leaving
  nothing to say which tokens the declaration owns. The sniff refuses to guess.
- **Not auto-fixable** — matching PHPMD. Renaming a variable means rewriting
  every read and write of it, and for a property or parameter every caller
  too, so there is no safe mechanical rewrite.
- **Severity** — the sniff reports errors directly, so there is no built-in
  warning to raise: `phpcs` already exits non-zero on a violation, the way
  `phpmd` does.

## Divergence from PHPMD

Four shapes are reported here that `phpmd` stays silent on. Every one is a
genuine violation of the rule as PHPMD states it, and reporting it is the safe
direction: this ruleset exists so `phpmd` does not have to run, and reporting
more than `phpmd` never leaves a real violation unreported, while falling
silent where `phpmd` speaks would.

1. **A name whose first occurrence sits inside a `->` or `::` chain.** PHPMD
   exempts the whole `MemberPrimaryPrefix` subtree, which swallows the
   arguments of the call as well as the object it is called on:

   ```php
   $this->run($ab);    // phpcs reports $ab; phpmd does not
   Other::stat($cd);   // phpcs reports $cd; phpmd does not
   ```

   The same exemption is why `self::$sa = 2;` written before an ordinary local
   `$sa` silences that local in `phpmd`. Here the access is not an occurrence
   at all, so the local is still reported.
2. **A name declared inside a `catch` block's body.** PHPMD exempts everything
   under the `CatchStatement` node, not just the variable the `catch` binds.
   The bound variable itself is exempt in both tools.
3. **A variable in procedural, file-level code.** pdepend hands `phpmd` class,
   trait, function and method nodes only, so a file-level variable is never
   visited at any threshold.
4. **A property, parameter or local of an anonymous class's methods.** pdepend
   builds no class node for `new class`, the same blind spot documented for
   [ShortMethodName](naming-shortmethodname.md).

Two shapes are reported *less*.

A short parameter of a **trait method**, which `phpmd` prints twice. pdepend
hands it over once under the trait node and again under the method node, and
PHPMD's per-name map is reset between the two. The violation is reported
either way; only the duplicate is dropped.

**`$this`, however it is written.** This is the one place the ruleset is
genuinely quieter than `phpmd`, and the reason is that the report cannot be
acted on: PHP forbids assigning `$this`, so no rename silences it and a user
meeting it could only add `this` to the `exceptions` list. `this` is four
characters, so nothing changes at the default `minimum` of 3 — the exclusion
is what keeps a ruleset that raises `minimum` to 5 from reporting every
`$this` in the codebase.

`phpmd` is not uniform here. pdepend folds a chain's receiver into a
`MemberPrimaryPrefix` and builds no node for it, so `phpmd` is silent on the
first two below and reports the rest at `minimum` 5:

```php
$this->value;         // phpmd silent — receiver of a member chain
"{$this->value}";     // phpmd silent — braced interpolation parses the chain
return $this;         // phpmd reports $this
"{$this}"; "$this";   // phpmd reports $this
"$this->value";       // phpmd reports $this — the simple-syntax parser
<<<EOT $this->value   // phpmd reports $this — likewise
```

Every behaviour above is pinned by a fixture under
`tests/fixtures/ShortVariableSniff/` — `divergences.php`, `static-access.php`,
`contexts.php`, `trait-method.php`, `reopened-tags.php`,
`multiline-string.php`, `implicit-receiver.php` and
`malformed-declaration.php` — and every line
asserted against `failing.php` and `passing.php` was cross-checked against a
live `phpmd` 2.15 run of `rulesets/naming.xml/ShortVariable` from a cold
pdepend cache.
