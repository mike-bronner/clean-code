# PHPMD/CleanCode: UndefinedVariable

## Standard

- A variable must be defined before it is read.

**Why:**

- Reading an undefined variable is a bug, not a style choice: PHP evaluates it
  to `null` and raises a warning at runtime, so the defect surfaces in
  production instead of in the linter.
- The usual cause is a typo in a variable name, which no other rule catches.

PHPMD flags this as `UndefinedVariable` in its Clean Code ruleset (since PHPMD
2.8.0). It has no configurable thresholds.

```php
function printX() {
    echo $x;   // $x was never defined
}
```

_Source: [phpmd.org/rules/cleancode.html](https://phpmd.org/rules/cleancode.html)_

## Enforceability — Tier 1 (existing sniff)

No PHPCS, Generic, or Slevomat sniff covers undefined variables. The
well-known external standard
[`sirbrillig/phpcs-variable-analysis`](https://github.com/sirbrillig/phpcs-variable-analysis)
does, through its single `VariableAnalysis.CodeAnalysis.VariableAnalysis`
sniff, wired into the master `rules.xml`
([#85](https://github.com/mike-bronner/phpcs-rules/issues/85)).

- **Detection** — every read of a name the scope never assigns is flagged at
  its own line, whether the read is a plain variable
  (`VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable`), an
  array element, a string interpolation, or an `unset()` argument
  (`…VariableAnalysis.UndefinedUnsetVariable`).
- **Not auto-fixable** — an undefined variable has no machine-derivable value,
  so there is nothing for `phpcbf` to write. Both codes are reported without a
  fixer hook, matching PHPMD, which reports rather than rewrites.
- **Reported as an error, not a warning** — the sniff reports warnings out of
  the box; `rules.xml` raises the severity, as it does for `Squiz.PHP.Eval`. A
  warning leaves `phpcs` exiting 0 on an undefined variable, which would mean
  `phpmd` still had to run for this rule — the one thing this issue exists to
  stop.
- **Definitions the sniff already understands** — assignments, parameters,
  `foreach` values, `catch` variables, `list()`/`[]` destructuring, `use`
  captures on closures, by-reference parameters, superglobals, and `$this`.
  None of these needs configuration.

### Codes deliberately excluded

The sniff is broader than PHPMD's rule: it emits six codes. `rules.xml`
excludes the four that are not about reading an undefined variable, so this
standard does not quietly deliver rules that belong elsewhere.

| Excluded code | Why it is not this rule |
|---|---|
| `UnusedVariable` | An *unused* variable, not an undefined one. That is PHPMD's `UnusedLocalVariable` ([#118](https://github.com/mike-bronner/phpcs-rules/issues/118)) and `UnusedFormalParameter` ([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)). `UnusedFormalParameter` now ships through `Generic.CodeAnalysis.UnusedFunctionParameter`, so this exclude also stops the two sniffs reporting the same unused parameter twice. |
| `VariableRedeclaration` | Redeclaring a variable that *is* defined — the opposite case. No PHPMD counterpart. |
| `SelfOutsideClass` | A `self::` scope error, not a variable definition. |
| `StaticOutsideClass` | A `static::` scope error, not a variable definition. |

### Where the sniff and PHPMD differ

The sniff is not a drop-in match for PHPMD, and no property makes it one — the
sniff's public properties are allowlists for names and unused-variable
handling; none toggles statement-order or closure scoping. Rather than weaken
a fixture to force agreement, the differences are recorded here and pinned by
`tests/fixtures/VariableAnalysisSniff/divergences.php`.

The two tools ask different questions. PHPMD asks whether the enclosing method
assigns the name *anywhere*. The sniff asks whether an assignment has *already
been reached*, in this scope, at the point of the read. Neither asks whether
the assignment runs on every path.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| Read of a name the method never assigns | flags | flags |
| Read placed above its own assignment, same scope | silent | **flags** |
| Closure local read outside its closure | silent | **flags** |
| Name assigned on one branch, read unconditionally after | silent | silent |

Both extra reports are kept: each is a real defect that evaluates to `null` at
runtime, so the stricter behaviour is an improvement on PHPMD, not a false
positive. Adopting this ruleset can therefore surface findings a previous
`phpmd` run did not.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/cleancode.xml/UndefinedVariable`, and
`phpcs --standard=rules.xml`.

Ruleset-integration tests covering compliant code, per-line violation
reporting, the error severity, the absence of a fixer, both halves of the
exclude list, and the divergences above live at
`tests/Ruleset/UndefinedVariableTest.php`. The sniff is also in the generic
three-fixture sweep in `tests/Contract/SniffContractTest.php`.

Its fixtures follow the contract CONTRIBUTING.md prescribes, under
`tests/fixtures/VariableAnalysisSniff/`: `passing.php` for code the rule must
stay silent on and `failing.php` for the parity set, plus `divergences.php` and
`excluded-codes.php` for the shapes that belong to neither. There is no
`autofixed.php`, because the rule is not fixable — a test runs the real fixer
over `failing.php` and asserts its output is byte-identical to the input, so
"unfixable" is measured rather than assumed.

## What remains code review

**A variable assigned on one branch and read unconditionally afterwards.**

```php
if ($flag) {
    $result = 'set';
}

return $result;   // null whenever $flag is false — neither tool flags this
```

Both tools reason about scope membership, not path reachability, so both stay
silent. Replacing `phpmd` with this ruleset loses no coverage here — PHPMD
never caught it either — but the shape is a real defect and needs a reader.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.
