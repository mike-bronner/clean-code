# PHPMD/UnusedCode: UnusedFormalParameter

## Standard

- A declared parameter must be used in the body that declares it.

**Why:**

- A parameter nothing reads is dead weight in the signature: every caller still
  has to supply it, and every reader still has to work out what it is for.
- It is usually the residue of a refactor — the body stopped using the value and
  the signature was never trimmed — so it also hides how much a method really
  needs.

PHPMD flags this as `UnusedFormalParameter` in its Unused Code ruleset. It has
no configurable thresholds.

```php
private function bar($howdy) {
    // $howdy is not used
}
```

_Source: [phpmd.org/rules/unusedcode.html](https://phpmd.org/rules/unusedcode.html)_

## Enforceability — Tier 1 (existing sniff)

PHP_CodeSniffer ships this rule as `Generic.CodeAnalysis.UnusedFunctionParameter`,
wired into the master `rules.xml`
([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)). No custom
sniff and no Slevomat rule is needed.

- **Detection** — a parameter no statement in the body reads is flagged on the
  declaration line, in functions, methods, constructors, closures, and arrow
  functions. A read counts whether it is plain (`$name`), inside an interpolated
  string or heredoc, or inside a nested closure. A *dynamic* read — `${'name'}`
  — does not count in either tool; both report the parameter as unused.
- **Not auto-fixable** — deleting a parameter changes the signature and breaks
  every caller, so there is nothing safe for `phpcbf` to write. Every code is
  reported without a fixer hook, matching PHPMD, which reports rather than
  rewrites.
- **Reported as an error, not a warning** — the sniff reports warnings out of
  the box; `rules.xml` raises the severity, as it does for `Squiz.PHP.Eval` and
  `VariableAnalysis`. A warning leaves `phpcs` exiting 0 on an unused parameter,
  which would mean `phpmd` still had to run for this rule — the one thing this
  issue exists to stop.
- **Exemptions both tools already share** — a bodyless declaration (an interface
  method or an `abstract` method) cannot use anything, so neither tool speaks
  about it; a promoted constructor property becomes class state, so an
  unmentioning body is not a defect; and the magic methods whose signature PHP
  fixes (`__get`, `__set`, `__isset`, `__unset`, `__call`, `__callStatic`,
  `__set_state`) are skipped. `__invoke` and `__construct` are *not* magic for
  this purpose in either tool — their signatures are the author's own.
- **`ignoreTypeHints`** — the sniff's only property, an allowlist of type hints
  to exempt. PHPMD has no equivalent concept, so it is left at its empty
  default.

### Codes deliberately excluded

The sniff emits nine codes. It never changes its *verdict* for an inherited
signature — it changes the error *code*, to a `FoundInExtendedClass…` or
`FoundInImplementedInterface…` variant, whenever the enclosing class extends a
class or implements an interface. PHPMD instead stays **silent** on a parameter
that only exists to satisfy an inherited signature.

Excluding those six codes is what buys that exemption, and it is the whole
reason the sniff needs configuring:

| Excluded code | Why it is excluded |
|---|---|
| `FoundInExtendedClass` | The class extends another, so the signature may be the parent's. PHPMD exempts an override. |
| `FoundInExtendedClassBeforeLastUsed` | Same case; the sniff only distinguishes where the unused parameter sits. |
| `FoundInExtendedClassAfterLastUsed` | Same case. |
| `FoundInImplementedInterface` | The class implements an interface, so the signature may be the interface's. PHPMD exempts an implementation. |
| `FoundInImplementedInterfaceBeforeLastUsed` | Same case. |
| `FoundInImplementedInterfaceAfterLastUsed` | Same case. |

The three plain codes stay — `Found`, `FoundBeforeLastUsed`,
`FoundAfterLastUsed` — because they fire on a plain function, a closure, or a
method of a class that inherits nothing, where no signature is imposed from
outside and an unused parameter is simply dead.

### Where the sniff and PHPMD differ

The sniff is not a drop-in match for PHPMD, and no property makes it one:
`ignoreTypeHints` exempts by type hint and cannot express any of the shapes
below. Rather than weaken a fixture to force agreement, the differences are
recorded here and pinned by
`tests/fixtures/UnusedFunctionParameterSniff/divergences.php`.

The two tools decide the inheritance exemption differently. PHPMD asks whether
this exact method is the *first declaration* of its name — walking the parent
class and interfaces — and also exempts any method whose docblock carries
`@inheritdoc`. The sniff asks only whether the *enclosing class* extends or
implements anything, so its answer is per-class, not per-method. The excludes
above adopt the sniff's coarser answer.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| Unused parameter in a plain function or an inheritance-free class | flags | flags |
| Unused parameter named only in a docblock | flags | flags |
| Bodyless interface or `abstract` declaration | silent | silent |
| Genuine override of a parent or interface method | silent | silent |
| Method carrying `@inheritdoc` | silent | silent |
| Unused promoted constructor property | silent | silent |
| Closure parameter | silent | **flags** |
| Arrow-function parameter | silent | **flags** |
| Parameter reachable only through `func_get_args()` | silent | **flags** |
| Empty or comment-only body | flags | **silent** |
| `__unserialize()` | flags | **silent** |
| Non-override method in a class that extends or implements | flags | **silent** |

Reading the last six rows:

- **Closures and arrow functions.** PHPMD's rule visits functions and methods
  only, so a closure's dead parameter is invisible to it. The extra reports are
  kept: each is the same defect in a different construct. Adopting this ruleset
  can therefore surface findings a previous `phpmd` run did not.
- **`func_get_args()`.** PHPMD treats every parameter as used once the body
  calls `func_get_args()`; the sniff walks tokens, sees no `$collected`, and
  reports it. **This is the one shape where this ruleset over-reports.** There
  is no property that suppresses it. Silence it at the call site with a
  `phpcs:ignore` comment if the variadic-by-`func_get_args` idiom is wanted.
- **Empty or comment-only bodies.** The sniff exempts them outright, so a class
  stubbing out several interface methods is not told off for each. PHPMD
  reports them. Coverage lost relative to `phpmd`.
- **`__unserialize()`.** The sniff's magic-method list is wider than PHPMD's —
  it also carries `__destruct`, `__sleep`, `__wakeup`, `__serialize`,
  `__unserialize`, `__toString`, `__clone`, and `__debugInfo`. Only
  `__unserialize(array $data)` takes a parameter, so it is the only one where
  the wider list changes an outcome.
- **Non-override methods inside a class that extends or implements.** This is
  the price of the excludes: the sniff cannot say whether a given method is an
  override, so silencing the override case silences its neighbours in the same
  class too. Coverage lost relative to `phpmd`, traded for the exemption PHPMD
  itself applies.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/unusedcode.xml/UnusedFormalParameter`, and
`phpcs --standard=rules.xml`.

Ruleset-integration tests covering compliant code, per-line and per-column
violation reporting, the error severity, the absence of a fixer, both halves of
the exclude list, and the divergences above live at
`tests/Ruleset/UnusedFormalParameterTest.php`. The sniff is also in the generic
three-fixture sweep in `tests/Contract/SniffContractTest.php`.

Its fixtures follow the contract CONTRIBUTING.md prescribes, under
`tests/fixtures/UnusedFunctionParameterSniff/`: `passing.php` for code the rule
must stay silent on and `failing.php` for the parity set, plus `divergences.php`
and `excluded-codes.php` for the shapes that belong to neither. There is no
`autofixed.php`, because the rule is not fixable — a test runs the real fixer
over `failing.php` and asserts its output is byte-identical to the input, so
"unfixable" is measured rather than assumed.

## What remains code review

**An unused parameter in a class that extends or implements something, where
the method is the class's own.**

```php
class DerivedLedger extends Ledger
{
    public function archive(string $unused): void   // not an override
    {
        echo 'archived';
    }
}
```

The sniff decides the inheritance exemption per class, not per method, so
silencing the genuine override silences this too. `phpmd` did catch it, so this
is the one place where dropping `phpmd` loses coverage that a reader has to
replace.

**A parameter kept only for a signature no linter can see** — a framework
callback, a queue handler, an event listener. Both tools flag it in an
inheritance-free class, and neither can tell it apart from dead weight. Suppress
it deliberately with `phpcs:ignore` rather than by relaxing the rule.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.
