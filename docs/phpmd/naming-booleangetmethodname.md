# PHPMD Naming: BooleanGetMethodName

## Rule

- A method that is declared to return a boolean is not named `getX()`.

**Why:** a getter promises to hand back a value. A method that answers yes or
no reads as a question at every call site, so the convention is `isX()` or
`hasX()`.

```php
// PHPMD (and this ruleset) flags this:
class Foo {
    /**
     * @return boolean
     */
    public function getFoo() {}
}
```

_Source: [phpmd.org/rules/naming.html](https://phpmd.org/rules/naming.html)
(PHPMD Naming ruleset, since PHPMD 0.2)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Naming/BooleanGetMethodName` | `CleanCode.Naming.BooleanGetMethodName` (message code `.Found`) |

No existing PHPCS or Slevomat sniff expresses this rule. The four nearest
candidates were run against
`tests/fixtures/BooleanGetMethodNameSniff/failing.php`, whose twelve methods
are all boolean getters. None of them answers the question this rule asks —
three are silent on ten of the twelve and speak about the two remaining ones
for an unrelated reason, and the fourth reports all twelve for the opposite
reason:

- `PSR1.Methods.CamelCapsMethodName` — the *casing* of a method name, whatever
  it returns. Reports line 52 only, because `GetDraft` starts with a capital.
- `Generic.NamingConventions.CamelCapsFunctionName` — the same question, and
  the same single line.
- `Squiz.NamingConventions.ValidFunctionName` — casing plus the underscore
  convention for non-public methods. Reports lines 36 and 52, for the leading
  underscore and the capital. Rename both to `isArchived()` and `isDraft()`
  and all three sniffs fall silent while the rule they are standing in for
  would have nothing left to say either — which is the point: they read the
  name's *shape*, never what the method returns.
- `SlevomatCodingStandard.TypeHints.ReturnTypeHint` — whether a return type is
  declared at all, never what the method is called. Reports all twelve lines,
  because the fixture declares `@return` without a native hint; rename every
  one of them to `isX()` and it reports the same twelve lines unchanged.

So the rule is the custom `CleanCode.Naming.BooleanGetMethodName` sniff, registered automatically
from `CleanCode/Sniffs/` when the standard loads
([#116](https://github.com/mike-bronner/phpcs-rules/issues/116)). Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd` does not have
to run separately for it.

- **Detection** — a method is a boolean getter when all three hold:
  - its name matches `(^_?get)i` — PHPMD's own pattern, taken from PHPMD
    2.15.0's source rather than from the `^get[A-Z_]` the rule page
    paraphrases it as. The match is case-insensitive, allows one leading
    underscore, and does **not** require a capital after `get`, so
    `getterCached()` and `GetDraft()` are both getters to PHPMD;
  - it is declared to return a boolean, by its `@return` tag or by its native
    return type (see below);
  - `checkParameterizedMethods` allows it (see *Configuration*).

  Each offending method is reported at its own **name** token, since the name
  is what the rule asks to be changed.
- **Methods only** — PHPMD's rule is `MethodAware`, so a plain function, a
  closure, an arrow function, and a named function declared *inside* a method
  are all left alone. Only the innermost enclosing scope decides: a nested
  function still lists its enclosing class among PHPCS's `conditions`, so
  reading any class-like condition rather than the innermost one would report
  it.
- **Two sources for the return type, where PHPMD has one.** PHPMD reads the doc
  comment only. This sniff reads the native return type as well, which
  [#116](https://github.com/mike-bronner/phpcs-rules/issues/116) requires and
  which is the shape that actually occurs here: `CleanCode/ruleset.xml` requires a native
  return type on every method
  (`SlevomatCodingStandard.TypeHints.ReturnTypeHint`), so the native
  declaration is the one that is always present and the doc comment is the
  optional extra.
- **Both sources are read the same way** — `bool`, `boolean`, `?bool`, and
  `bool|null` all count, because the `?` and the `null` member only add a third
  state to the same yes/no answer. A wider union such as `bool|string` does
  not: that method returns a value, not an answer.
- **A doc comment has to be attached** — the search steps over attributes, in
  either order, but stops at anything else. A comment separated from the
  declaration by a constant or a property belongs to that member, not to the
  method.
- **An `@return` tag with no type contributes nothing** — the search for the
  type stops at whichever comes first, a doc-comment string or the next tag, so
  a bare `@return` followed by `@see bool` does not borrow the latter's text.
- **Error severity** — the sniff reports errors, so a boolean getter fails a
  `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed in
  `CleanCode/ruleset.xml`, unlike the two Tier 1 mappings.
- **Not auto-fixable** — matching PHPMD. Renaming a method rewrites every call
  site, and `is` versus `has` is a judgement about what the method asks;
  neither is a mechanical rewrite.

## Configuration

The property is spelled exactly as PHPMD spells it and matches the way PHPMD
matches, so an existing PHPMD configuration for this rule transfers verbatim:

```xml
<rule ref="CleanCode.Naming.BooleanGetMethodName">
    <properties>
        <property name="checkParameterizedMethods" value="true"/>
    </properties>
</rule>
```

- `checkParameterizedMethods` — defaults to `false`, exactly as PHPMD's
  `naming.xml` ships it. Left off, **every** boolean getter is reported,
  however many parameters it takes. Turned on, the rule **narrows** to
  parameterless getters, on the reasoning that a method taking arguments
  computes an answer rather than exposing a stored one.

  PHPMD's own property description — "Applies only to methods without parameter
  when set to true" — is easy to read backwards, so the direction was taken
  from the implementation (`isParameterizedOrIgnored()` returns
  `$node->getParameterCount() === 0` when the property is on, and `true` when
  it is off) and confirmed against a live PHPMD 2.15.0 run over
  `tests/fixtures/BooleanGetMethodNameSniff/configured.php`: four reports with
  the property off, one with it on.

  Every parameter counts, optional and variadic alike — PHPMD asks PDepend for
  the parameter *count*, so a signature that can be called with no arguments is
  not the same thing as a signature that declares none.

## Divergences from PHPMD

This ruleset is stricter on six shapes and looser on none, so no PHPMD finding
is lost. Every claim below was checked against a live PHPMD 2.15.0 run over the
fixtures named.

| Shape | PHPMD | This ruleset | Pinned by |
| --- | --- | --- | --- |
| `function getVisible(): bool` — a native return type, no `@return` tag | silent | reported | `divergences.php:13` |
| `function getPublished(): ?bool` | silent | reported | `divergences.php:21` |
| `function getArchived(): bool\|null` | silent | reported | `divergences.php:29` |
| `@return ?bool` in the doc comment | silent | reported | `divergences.php:40` |
| `@return bool\|null` in the doc comment | silent | reported | `divergences.php:51` |
| A method of an *anonymous* class | silent | reported | `divergences.php:67` |

1. **Native return types.** PHPMD matches
   `(\*\s*@return\s+bool(ean)?\s)i` against the doc comment and looks nowhere
   else, so a native declaration never reaches its check. Enforcing that half
   is required by
   [#116](https://github.com/mike-bronner/phpcs-rules/issues/116), and it is
   what makes the rule useful here.
2. **Nullable and union spellings in the doc comment.** PHPMD's pattern wants
   `bool` or `boolean` immediately after `@return `, and whitespace immediately
   after that, so a leading `?` and a trailing `|null` each hide the type from
   it. Both spell the same yes/no answer.
3. **Methods of anonymous classes.** PDepend does not surface one to a
   `MethodAware` rule, so PHPMD never visits it. A method of an anonymous class
   is still a method.

All six extra reports are true defects, so they are kept — the same call
`CleanCode/ruleset.xml` records for `VariableAnalysis` under
[#85](https://github.com/mike-bronner/phpcs-rules/issues/85).

## Overlap with the Models: Naming Conventions standard (#44)

[#44](https://github.com/mike-bronner/phpcs-rules/issues/44) is a broader,
Laravel-specific naming standard: boolean *properties* named as a yes/no
question, boolean methods named `has<Condition in past tense>`, `find`- and
`get`-prefixed query methods, and Laravel attribute accessors.

The two **complement** each other and neither subsumes the other:

- This rule speaks only about a method already declared to return a boolean,
  and only about the `get` prefix being wrong. It says nothing about which of
  `is`, `has`, or `should` replaces it, nothing about properties, and nothing
  about query methods or accessors.
- #44's boolean half wants a *specific* replacement (`has<Condition>`) and
  covers properties too; its query half governs `get`-prefixed methods that
  return a collection, which this rule leaves alone because they are not
  boolean.

So #44 stays open. When it lands, the two overlap on exactly one shape — a
boolean-returning `getX()` method — and #44's sniff can either exclude that
shape or defer to this one, the way `CleanCode/ruleset.xml` already dedupes the operator
spacing sniffs.
