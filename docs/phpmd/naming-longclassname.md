# PHPMD Naming: LongClassName

## Rule

- A class, interface, trait, or enum name must not be longer than 40
  characters.

**Why:** a name that long is usually a symptom rather than a style problem. It
means the type has accumulated more than one responsibility and the name has
grown to describe all of them.

```php
// PHPMD (and this ruleset) flags this:
class ATooLongClassNameThatHintsAtADesignProblem {}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 2.9)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/LongClassName` | `CleanCode.Naming.LongClassName` (message code `.TooLong`) |

No PHPCS, Generic, or Slevomat sniff measures a type name's *length* — the
naming sniffs that ship with them all check casing, prefixes, or suffixes
instead (`Squiz.Classes.ValidClassName`,
`Generic.NamingConventions.AbstractClassNamePrefix`,
`Generic.NamingConventions.InterfaceNameSuffix`,
`Generic.NamingConventions.TraitNameSuffix`,
`SlevomatCodingStandard.Classes.Superfluous*Naming`). Slevomat's
`Classes.ClassLength` counts a class's *lines*, not its name. So this rule is
carried by a custom sniff, wired into the master `CleanCode/ruleset.xml`
([#101](https://github.com/mike-bronner/clean-code/issues/101)). Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **Detection** — every `class`, `interface`, `trait`, and `enum` declaration is
  measured and reported at its own declaration keyword. PHPMD's rule declares
  itself `ClassAware`, `InterfaceAware`, `TraitAware`, and `EnumAware`, so all
  four are in scope for both tools.
- **The declared short name is what is measured.** A long namespace does not
  contribute to the length, and a reference to a long-named type declared
  elsewhere (an import, a type hint, a `new`) is not a declaration and is not
  measured.
- **Anonymous classes are never reported.** They have no declared name to
  measure. PHPCS tokenises `new class {}` as `T_ANON_CLASS`, which the sniff
  does not register.
- **Reported strictly above the threshold.** A name of exactly 40 characters
  passes; 41 fails. This matches PHPMD, which returns early on
  `$length <= $threshold`.
- **Not auto-fixable** — matching PHPMD. Renaming a type means rewriting every
  reference to it across the codebase, which a single-file, token-based fixer
  cannot do safely. The sniff reports errors with no fixer attached.
- **Error severity.** The sniff raises errors out of the box, so `phpcs` exits
  non-zero on a violation exactly as `phpmd` does. Unlike the `Squiz.PHP.Eval`
  and `VariableAnalysis` mappings, no `<type>error</type>` override is needed in
  `CleanCode/ruleset.xml`.

## Configurable properties

The sniff carries PHPMD's three properties under PHPCS's camelCase spelling,
with PHPMD's defaults. `CleanCode/ruleset.xml` configures none of them, so the defaults
apply as shipped.

| PHPMD property | Sniff property | Default | Meaning |
| --- | --- | --- | --- |
| `maximum` | `maximum` | `40` | The reporting threshold. A name longer than this is flagged. |
| `subtract-prefixes` | `subtractPrefixes` | `''` | Comma-separated prefixes that do not count towards the length. |
| `subtract-suffixes` | `subtractSuffixes` | `''` | Comma-separated suffixes that do not count towards the length. |

```xml
<rule ref="CleanCode.Naming.LongClassName">
    <properties>
        <property name="maximum" value="50"/>
        <property name="subtractPrefixes" value="Abstract"/>
        <property name="subtractSuffixes" value="Repository,Factory"/>
    </properties>
</rule>
```

The subtraction rules are PHPMD's, reproduced exactly rather than tidied up
(`PHPMD\Utility\Strings::lengthWithoutPrefixesAndSuffixes()`):

- **At most one suffix and at most one prefix** are subtracted, however many
  entries match.
- The entry subtracted is the **first one in the configured list** that matches,
  not the longest. With `subtractSuffixes="Repository,MockRepository"`, a name
  ending in `MockRepository` loses 10 characters, not 14.
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

## Verification

The three fixtures under `tests/fixtures/LongClassNameSniff/` were run through a
live PHPMD 2.15.0 install as well as through this sniff, and the two agree line
for line:

| Fixture | Prefixes / suffixes | PHPMD and the sniff both report |
| --- | --- | --- |
| `passing.php` | defaults | nothing |
| `failing.php` | defaults | lines 10, 14, 19, 26, 31 |
| `subtraction.php` | defaults | lines 13, 18, 24, 30, 37, 43 |
| `subtraction.php` | `Abstract` / `Repository,Factory,MockRepository` | lines 37, 43 |
| `subtraction.php` | `Abstract,AbstractWarehouse` / — | lines 18, 24, 30, 37, 43 |
| `subtraction.php` | `" , Abstract , "` / — | lines 18, 24, 30, 37, 43 |

`tests/Standards/LongClassNameTest.php` pins each of these. The last three rows
are the ones that pin PHPMD's stop-at-the-first-match behaviour and its
list-trimming: were either loop to keep going past its first hit, or were the
empty entries kept, one of those rows would come out shorter.

`tests/fixtures/LongClassNameSniff/nameless.php` — a bare `class` keyword with
nothing after it — is a PHPCS tokenizer edge case rather than a parity claim,
and is covered by the sniff's own test only.
