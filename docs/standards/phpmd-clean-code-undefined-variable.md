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

- **Detection** — every read of a name that was never assigned is flagged at
  its own line, whether the read is a plain variable
  (`VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable`), an
  array element, a string interpolation, a read placed above its own
  assignment, or an `unset()` argument
  (`…VariableAnalysis.UndefinedUnsetVariable`).
- **Not auto-fixable** — an undefined variable has no machine-derivable value,
  so there is nothing for `phpcbf` to write. Both codes are reported as
  unfixable warnings, matching PHPMD, which reports rather than rewrites.
- **Definitions the sniff already understands** — assignments, parameters,
  `foreach` values, `catch` variables, `list()`/`[]` destructuring, `use`
  captures on closures, by-reference parameters, superglobals, and `$this`.
  None of these needs configuration.

### Codes deliberately excluded

The sniff is broader than PHPMD's rule: it emits five codes. `rules.xml`
excludes the three that are not about reading an undefined variable, so this
standard does not quietly deliver rules that belong elsewhere.

| Excluded code | Why it is not this rule |
|---|---|
| `UnusedVariable` | An *unused* variable, not an undefined one. That is PHPMD's `UnusedLocalVariable` ([#118](https://github.com/mike-bronner/phpcs-rules/issues/118)) and `UnusedFormalParameter` ([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)). |
| `VariableRedeclaration` | Redeclaring a variable that *is* defined — the opposite case. No PHPMD counterpart. |
| `SelfOutsideClass` / `StaticOutsideClass` | A `self::`/`static::` scope error, not a variable definition. |

Ruleset-integration tests covering compliant code, per-line violation
reporting, the unfixable-warning severity, and both halves of the exclude list
live at `tests/Ruleset/UndefinedVariableTest.php`.

## What remains code review

Nothing — this rule is fully machine-enforced, and `phpmd` no longer needs to
run separately for it.
