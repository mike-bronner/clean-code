# PHPMD Naming: ShortClassName

## Rule

- A class, interface, trait, or enum is not declared with a name shorter than
  three characters.

**Why:** a name too short to say what the type is forces every reader to open
the file to find out, and it collides with the next abbreviation as soon as the
codebase grows.

```php
// PHPMD (and this ruleset) flags this:
class Fo {

}

interface Fo {

}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 2.9)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/ShortClassName` | `CleanCode.Naming.ShortClassName` (message code `.TooShort`) |

No existing PHPCS or Slevomat sniff expresses this rule. Every candidate was run
against `tests/fixtures/ShortClassNameSniff/failing.php`; none of them measures a
declaration name's length, and four reported nothing at all on its five short
declarations:

- `Squiz.Classes.ValidClassName` — a name's *casing*. `Fo`, `Ab`, `Tr`, `En`,
  and `X` are all valid PascalCase.
- `SlevomatCodingStandard.Classes.ClassLength` — a class *body's* line count,
  not its name.
- `SlevomatCodingStandard.Classes.SuperfluousInterfaceNaming` and
  `.SuperfluousTraitNaming` — a redundant `Interface`/`Trait` *suffix*, the
  opposite direction.

Two candidates do report on that fixture, and neither for its subject:

- `Generic.NamingConventions.InterfaceNameSuffix` and `.TraitNameSuffix` require
  an `Interface`/`Trait` suffix, a convention this ruleset does not adopt. They
  speak only about the interface on line 7 and the trait on line 11, ignoring the
  short class and enum entirely, and a suffixed name satisfies them however
  little it says.
- `PSR1.Classes.ClassDeclaration`, already active through the `PSR12` reference
  in `CleanCode/ruleset.xml`, reports the fixture for having no namespace and several types
  per file — again, nothing to do with name length.

So the rule is the custom `CleanCode.Naming.ShortClassName` sniff, registered
automatically from `CleanCode/Sniffs/` when the standard loads
([#103](https://github.com/mike-bronner/clean-code/issues/103)). Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd`
does not have to run separately for it.

- **Detection** — the declaration's **unqualified** name is measured in *bytes*
  (`strlen()`, as PHPMD's own rule does) and reported when it is shorter than
  `minimum`. A name of exactly `minimum` bytes passes: the property is a
  reporting threshold, and PHPMD returns early on `strlen($name) >= $threshold`.
- **Bytes, not characters** — the two counts only agree on ASCII. A non-ASCII
  identifier is legal PHP, and under the shipped `minimum` of 3 a name like `Aé`
  (3 bytes, 2 characters) or `類` (3 bytes, 1 character) passes, while `Δ`
  (2 bytes) is reported. Byte counting is what PHPMD does, so this is parity and
  not a local choice — `tests/fixtures/ShortClassNameSniff/multibyte.php` pins
  it, and a live PHPMD run over that fixture agrees (see the table below). Note
  a byte count can only ever report a subset of what a character count would:
  a UTF-8 name is never fewer bytes than characters.
- **Four keywords, not two** — classes, interfaces, traits, and enums are all
  reported. PHPMD's rule class declares `ClassAware`, `InterfaceAware`,
  `TraitAware`, and `EnumAware`, so it speaks about all four despite the rule
  name and the phpmd.org description naming only the first two. Registering the
  same four is what keeps this sniff from being looser than the tool it
  replaces.
- **Error severity** — the sniff reports errors, so a short class name fails a
  `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed in
  `CleanCode/ruleset.xml`, unlike the two Tier 1 mappings.
- **Not auto-fixable** — matching PHPMD. Renaming a type rewrites every
  reference to it and, under PSR-4, the file it lives in; that is a refactor,
  not a mechanical rewrite.
- **Anonymous classes are never reported** — PHPCS gives one its own
  `T_ANON_CLASS` token, which the sniff does not register, and PDepend hands
  PHPMD a generated name well over any sensible threshold. Neither tool reports
  one.
- **The namespace is not measured** — `namespace Ab; class Widget {}` is clean.
  PHPCS's `getDeclarationName()` returns the declared name without its
  namespace, which is what PDepend's `getName()` gives PHPMD.
- **Only declarations are measured** — importing (`use App\Fo;`) or referencing
  (`Fo::class`) a short type name is not a violation for either tool. Short
  *method* and *variable* names are PHPMD's separate `ShortMethodName` and
  `ShortVariable` rules, not this one.

## Configuration

Both properties are spelled exactly as PHPMD spells them and match the way PHPMD
matches, so an existing PHPMD configuration for this rule transfers verbatim:

```xml
<rule ref="CleanCode.Naming.ShortClassName">
    <properties>
        <property name="minimum" value="4"/>
        <property name="exceptions" value="Log,URL,FTP"/>
    </properties>
</rule>
```

- `minimum` — the reporting threshold, in bytes. Defaults to `3`, exactly as
  PHPMD's `naming.xml` ships it.
- `exceptions` — a comma-separated list of names that are exempt however short
  they are. Each entry is trimmed and empty entries are dropped, as PHPMD's
  `Strings::splitToList()` does, so `Log, URL , FTP` and `Log,URL,FTP` are the
  same list. Matching is case-sensitive, as PHPMD's own `array_flip()` +
  `isset()` lookup is: with `Log` listed, a type named `log` is still reported.

`exceptions` defaults to the **empty string**, exactly as `naming.xml` ships it.
The `Log,URL,FTP` in PHPMD's property description is an *example* of what a
project might exempt, not a default — nothing is exempt out of the box. This is
the same call `docs/phpmd/cleancode-booleanargumentflag.md` records for that
rule's two properties.

Note that PHPMD's example exceptions are all exactly three characters, so under
the shipped `minimum` of 3 they already pass the length check and the list
changes nothing. `exceptions` only starts to matter once a project raises
`minimum`, which is why the example above raises it to 4.

Leave `exceptions` out of a ruleset rather than restating the default as
`value=""`: PHP_CodeSniffer turns an empty property value into `null`, and the
sniff's property is a typed `string`.

## Divergences from PHPMD

None on any file both tools can read. The detection is PHPMD's, statement for
statement — the same byte-length comparison, the same early return at the
threshold, the same trimmed comma-separated exceptions list, the same
case-sensitive lookup, and the same four class-like keywords — so no PHPMD
finding for this rule is lost, and none is added.

Checked against a live PHPMD 2.15.0 run over the fixtures, which reported the
same lines and the same message text as the sniff on every one:

| Run | PHPMD | This ruleset |
| --- | --- | --- |
| `failing.php`, shipped defaults | lines 3, 7, 11, 15, 19 | lines 3, 7, 11, 15, 19 |
| `passing.php`, shipped defaults | silent | silent |
| `exceptions.php`, shipped defaults | silent | silent |
| `exceptions.php`, `minimum=4` | lines 3, 7, 11, 19 | lines 3, 7, 11, 19 |
| `exceptions.php`, `minimum=4` + `exceptions="Log, URL , FTP"` | line 19 | line 19 |
| `multibyte.php`, shipped defaults | line 11 | line 11 |

The one file the two tools treat differently is a **half-written declaration** —
`nameless.php`, a `class` keyword with nothing after it. PHPCS tokenizes a file
mid-edit and hands it to the sniff, which passes over it because there is no
name to measure; PDepend cannot parse it at all and PHPMD aborts the file with
`Unexpected end of token stream`. Neither tool reports a violation, so no
finding is lost either way — it is a parse outcome, not a detection difference.
