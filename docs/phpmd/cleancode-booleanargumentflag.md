# PHPMD CleanCode: BooleanArgumentFlag

## Rule

- A method, function, closure, or arrow function does not take a boolean flag
  argument.

**Why:** a boolean flag argument means the callee holds two behaviours and the
caller picks one, which is a reliable indicator of a Single Responsibility
Principle violation. Extract each branch the flag selects into its own method.

```php
// PHPMD (and this ruleset) flags this:
class Foo {
    public function bar($flag = true) {
    }
}
```

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html)
(PHPMD CleanCode ruleset, since PHPMD 1.4.0)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `CleanCode/BooleanArgumentFlag` | `CleanCode.Functions.DisallowBooleanArgumentFlag` (message code `.Found`) |

No existing PHPCS or Slevomat sniff expresses this rule. The three nearest
candidates were run against
`tests/fixtures/DisallowBooleanArgumentFlagSniff/failing.php` and each reported
nothing on its fifteen boolean flag arguments:

- `SlevomatCodingStandard.Functions.UselessParameterDefaultValue` — a default
  value that can never be used, whatever its type.
- `Squiz.PHP.DisallowBooleanStatement` — a boolean operator forming a whole
  statement, not a parameter.
- `Generic.CodeAnalysis.UnusedFunctionParameter` — a parameter no body reads,
  whatever its type.

So the rule is the custom `CleanCode.Functions.DisallowBooleanArgumentFlag`
sniff, registered automatically from `CleanCode/Sniffs/` when the standard
loads ([#76](https://github.com/mike-bronner/clean-code/issues/76)). Running
`phpcs` with `CleanCode/ruleset.xml` therefore covers this rule, and `phpmd`
does not have to run separately for it.

- **Detection** — a parameter is a boolean flag when either half holds:
  - its native type declaration resolves to `bool` — `bool`, `?bool`,
    `bool|null`, `null|bool`;
  - its default value is the literal `true` or `false`, in any casing.

  Each offending parameter is reported at its own variable, so a signature with
  two flags earns two diagnostics. Named functions and methods, closures, and
  arrow functions are all scanned, in classes, interfaces, traits, enums, and at
  file scope.
- **Error severity** — the sniff reports errors, so a flag argument fails a
  `phpcs` run the way it fails a `phpmd` run. No `<type>` override is needed in
  `CleanCode/ruleset.xml`, unlike the two Tier 1 mappings.
- **Not auto-fixable** — matching PHPMD. Removing a flag argument splits the
  callee in two and rewrites every call site; that is a design change, not a
  mechanical rewrite.
- **Variadics are never flags** — `bool ...$flags` is a list of booleans rather
  than a branch selector, and PHP forbids a default value on a variadic, so
  PHPMD cannot report one either.
- **A wider union is not a flag** — `bool|string $value` carries a value, not a
  branch selector, and is left alone. Only `null` is stripped before the type is
  compared, because `?bool` differs from `bool` solely in having a third state.
- **A default has to be a literal** — `$mode = self::VERBOSE` and
  `$mode = !true` are not reported even when they evaluate to a boolean. PHPCS
  hands the sniff the default expression verbatim and PDepend cannot resolve
  either one, so both tools stay silent.

## Configuration

Both properties are spelled exactly as PHPMD spells them and match the way
PHPMD matches, so an existing PHPMD configuration for this rule transfers
verbatim:

```xml
<rule ref="CleanCode.Functions.DisallowBooleanArgumentFlag">
    <properties>
        <property name="exceptions" value="ReportRenderer,Publisher"/>
        <property name="ignorepattern" value="/^(__construct|get.*Filtered)$/"/>
    </properties>
</rule>
```

- `exceptions` — a comma-separated list of class names. A declaration is exempt
  when the *unqualified* name of its enclosing class, interface, trait, or enum
  is on the list; PDepend's `getName()`, which PHPMD compares against, drops the
  namespace. Matching is case-sensitive, as PHPMD's own `array_flip()` lookup
  is. A method of an anonymous class is never exempt: the anonymous class has no
  name to list, and the lookup stops there rather than inheriting the exemption
  of an enclosing named class.
- `ignorepattern` — a PCRE. A method or function whose name matches it is
  exempt. A pattern PHP cannot compile exempts nothing, so a configuration
  mistake over-reports rather than switching the rule off silently.

Both default to the empty string, exactly as PHPMD's `cleancode.xml` ships them.
Nothing is exempt out of the box, constructors included; the constructor in
PHPMD's property description is an *example* of what a project might exempt, not
a default. A project that finds promoted constructor properties noisy —
`public function __construct(private bool $verbose)` — is what `ignorepattern`
exists for.

## Divergences from PHPMD

This ruleset is stricter on three shapes and looser on none, so no PHPMD finding
is lost. Every claim below was checked against a live PHPMD 2.15.0 run over the
fixtures named.

| Shape | PHPMD | This ruleset | Pinned by |
| --- | --- | --- | --- |
| `function send(bool $silent)` — a `bool` type declaration with no default | silent | reported | `divergences.php:7` |
| A closure at file scope, `$fn = function ($force = true) {}` | silent | reported | `divergences.php:13` |
| A closure inside a method the `ignorepattern` exempts | silent | reported | `configured.php:9` |

1. **Type declarations.** PHPMD reads a parameter's resolved *default value* and
   reports when that value is exactly `true` or `false`; a type declaration
   never reaches its check. Enforcing the type half as well is required by
   [#76](https://github.com/mike-bronner/clean-code/issues/76), and it is what
   makes the rule useful here: `CleanCode/ruleset.xml` requires a native type hint on every
   parameter (`SlevomatCodingStandard.TypeHints.ParameterTypeHint`), so the
   untyped shape PHPMD keys on barely occurs in code this ruleset governs.
2. **Closures at file scope.** PHPMD's rule visits method and function nodes and
   searches each one's whole subtree, so it sees a closure nested inside a method
   but never a closure that is nested in nothing. The sniff registers closures
   and arrow functions in their own right and has no such blind spot.
3. **Closures inside an exempted method.** PHPMD tests `ignorepattern` against
   the enclosing method's name, so exempting `__construct` also drops a closure
   written inside it. A closure has its own signature and its own
   responsibility, so exempting a method by name does not exempt the callables
   written inside it.

All three extra reports are true defects, so they are kept — the same call
`CleanCode/ruleset.xml` records for `VariableAnalysis` under
[#85](https://github.com/mike-bronner/clean-code/issues/85).
