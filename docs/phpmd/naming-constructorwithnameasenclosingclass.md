# PHPMD Naming: ConstructorWithNameAsEnclosingClass

## Rule

- A constructor must be declared as `__construct()`, never as a method sharing
  the enclosing class's name.

**Why:** the PHP 4 constructor style was deprecated in PHP 7.0 and removed in
PHP 8.0. A method named after its class no longer constructs anything — it
reads as a constructor while being an ordinary method, which is the defect.

```php
// PHPMD (and this ruleset) flags this:
class MyClass
{
    public function MyClass() // PHP 4 style
    {
    }
}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 0.2)_

## Mapping — Tier 1 (existing sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/ConstructorWithNameAsEnclosingClass` | `Generic.NamingConventions.ConstructorName` (message code `.OldStyle`) |

Enforced by `Generic.NamingConventions.ConstructorName`, wired into the master
ruleset (`rules.xml`) — no custom sniff needed
([#113](https://github.com/mike-bronner/phpcs-rules/issues/113)). Running
`phpcs` with `rules.xml` therefore covers this rule, with the three exceptions
recorded under [Divergences](#divergences-from-phpmd) below.

- **Detection** — the sniff walks each class body and compares every method's
  name against the enclosing class's. PHPMD has no threshold or configurable
  property for this rule, so there is nothing to tune and `rules.xml` configures
  no `<properties>`.
- **Case-insensitive on both sides** — the sniff lowercases both names before
  comparing; PHPMD uses `strcasecmp()`. A method differing from its class only
  in case (`MIXEDCASENAME` in `class MixedCaseName`) is a PHP 4 constructor to
  each tool. This is verified behaviour, not an assumption: the boundary is
  pinned by `failing.php` line 37 and was confirmed by running PHPMD 2.15.0
  against that fixture.
- **No severity override needed** — unlike the `Squiz.PHP.Eval` and
  `VariableAnalysis` mappings, this sniff reports through `addError()` already,
  so `phpcs` exits non-zero on a violation without help from `rules.xml`. The
  test asserts it rather than assuming it, so a vendor change to warning
  severity surfaces as a failure instead of a silent hole in the mapping.
- **Not auto-fixable** — matching PHPMD. Renaming a PHP 4 constructor to
  `__construct()` is only safe once every call site and every subclass is known,
  which a single-file sniff cannot establish.
- **`OldStyleCall` excluded** — the sniff also reports PHP 4-style *calls* to a
  parent constructor (`parent::ParentName()`). PHPMD's rule only ever inspects a
  method's own declared name against its enclosing class, so a call site has no
  counterpart in the rule being replicated. `rules.xml` excludes the code rather
  than shipping it as a side effect, the same treatment the
  [UndefinedVariable](cleancode-undefinedvariable.md) mapping gives its extra
  codes.

## Divergences from PHPMD

This is not a drop-in match, and no property makes it one. Three shapes differ.
All were established by running PHPMD 2.15.0 against the fixtures named below,
not by reading its source, and each is pinned by a test in
`tests/Ruleset/ConstructorNameTest.php` so the gap cannot drift back into an
unearned parity claim.

| Shape | This ruleset | PHPMD 2.15.0 | Fixture |
| --- | --- | --- | --- |
| Namespaced class | flags | silent | `namespaced-divergence.php` |
| Enum with a method named after the enum | silent | flags | `phpmd-only-divergences.php` |
| Class declaring both `__construct` and a same-named method | silent | flags | `phpmd-only-divergences.php` |

**Namespaced classes — this ruleset is stricter, and stays that way.** PHPMD's
rule returns early unless the class sits in the global namespace. The exemption
is deliberate on PHPMD's part: PHP itself never treated a same-named method in a
namespaced class as a constructor. That is precisely why the extra report is
worth keeping — such a method reads as a constructor and is not one, in a
codebase where the namespaced form is the normal one. The sniff exposes no
property that would scope it to the global namespace, so the divergence is
documented rather than tuned away.

**Enums, and classes with both constructors — PHPMD is stricter, and wrongly
so.** PHPMD compares names only. An enum cannot declare a constructor at all,
and a class already declaring `__construct` has its real constructor there, so
in both cases the same-named method is an ordinary method rather than a PHP 4
constructor. The sniff stays silent on each — it registers on `T_CLASS` and
`T_ANON_CLASS` only, and suppresses `OldStyle` when the class's method list
already contains `__construct`. Both silences are the sniff's own behaviour, not
an effect of this ruleset's configuration, which the test asserts against the
unconfigured `Generic` standard.

**Traits and interfaces are not a divergence.** A method carrying its own
trait's or interface's name is flagged by neither tool: PHPMD skips an `ASTTrait`
parent and an `InterfaceNode` explicitly, and the sniff never registers on
`T_TRAIT` or `T_INTERFACE`. The two agree, and `passing.php` pins the agreement
by carrying both shapes as near-misses the sniff must stay silent on.
