# PHPMD/UnusedCode: UnusedLocalVariable

## Standard

- A local variable that is assigned must be read.

**Why:**

- An assignment nobody reads is dead code: it costs a reader attention and
  gives nothing back.
- It is often the visible half of a real bug — a result computed and then
  dropped, or a name misspelled at the point of use so the intended read
  silently went somewhere else.

PHPMD flags this as `UnusedLocalVariable` in its Unused Code ruleset.

```php
public function doSomething() {
    $i = 5; // unused
}
```

It has no thresholds, and two configurable properties:

| Property | Default | Meaning |
|---|---|---|
| `allow-unused-foreach-variables` | `false` | Skip names bound by a `foreach`, key and value alike |
| `exceptions` | *(empty)* | Comma-separated names to skip, written without the `$` |

_Source: [phpmd.org/rules/unusedcode.html](https://phpmd.org/rules/unusedcode.html)_

## Enforceability — Tier 1 (existing sniff)

No PHPCS, Generic, or Slevomat sniff covers unused local variables. The
well-known external standard
[`sirbrillig/phpcs-variable-analysis`](https://github.com/sirbrillig/phpcs-variable-analysis)
does, through the `UnusedVariable` code on its single
`VariableAnalysis.CodeAnalysis.VariableAnalysis` sniff, wired into the master
`rules.xml` ([#118](https://github.com/mike-bronner/phpcs-rules/issues/118)).

That is the same sniff already wired for PHPMD's `UndefinedVariable`
([#85](https://github.com/mike-bronner/phpcs-rules/issues/85),
[docs](cleancode-undefinedvariable.md)). Both rules share one `<rule>` block,
because a sniff has a single set of properties and two blocks configuring the
same class would silently fight over them.

- **Detection** — every assignment to a name the scope never reads is flagged
  at its own line, whether the name is a plain local, a `foreach` key or value,
  a destructured element, a `static` local, an imported `global`, a closure
  local, or a by-reference alias.
- **Not auto-fixable** — deleting an unused assignment can drop a side effect
  in the expression that produced it (`$unused = $this->save();`), so there is
  nothing safe for `phpcbf` to write. The code is reported without a fixer
  hook, matching PHPMD, which reports rather than rewrites.
- **Reported as an error, not a warning** — the sniff reports warnings out of
  the box; `rules.xml` raises the severity, as it does for `Squiz.PHP.Eval` and
  for `UndefinedVariable`. A warning leaves `phpcs` exiting 0 on an unused
  local, which would mean `phpmd` still had to run for this rule — the one
  thing this issue exists to stop.
- **Uses the sniff already understands** — reads, `compact()` string
  references, string and heredoc interpolation, by-reference arguments
  (`preg_match($p, $s, $matches)`), by-reference `foreach` bindings, and
  by-reference closure captures. None of these needs configuration.

### How the properties are configured

PHPMD's rule asks a narrower question than the sniff's `UnusedVariable` code
does, so three properties are set to close the gap. Each is pinned by a pair of
tests — the configured behaviour, and the same fixture under the opposite
setting — so a property deleted from `rules.xml` fails the suite rather than
quietly widening the rule.

| Property | Set to | Why |
|---|---|---|
| `allowUnusedFunctionParameters` | `true` | PHPMD splits formal parameters into its own `UnusedFormalParameter` rule ([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)); `UnusedLocalVariable` drops them in `removeParameters()`. The sniff has one code for locals and parameters alike, so the parameter half is silenced here until #120 lands. |
| `allowUnusedForeachVariables` | `false` | Matches PHPMD's own default for `allow-unused-foreach-variables`. The sniff's default is the opposite, hence the explicit set. |
| `allowUnusedVariablesInFileScope` | `true` | PHPMD's rule is `FunctionAware` and `MethodAware` only, so it never looks at a file's top-level scope. The sniff does by default. |

Two further properties are left at the sniff's defaults because those defaults
already match PHPMD:

- `allowUnusedCaughtExceptions` (`true`) — PHPMD's `isNameAllowedInContext()`
  exempts any variable bound by a `catch` statement. Pinned by a test anyway,
  because "agrees by default" is exactly the kind of agreement a vendor upgrade
  can end silently.
- `validUnusedVariableNames` (unset) — the analogue of PHPMD's `exceptions`,
  which is also empty by default. **The two take different separators:**
  PHPMD's `exceptions` is comma-separated, the sniff's
  `validUnusedVariableNames` is space-separated. A project moving its own
  `exceptions` list across has to swap the delimiter. A test pins the analogue
  by setting the property and asserting one name goes silent while the rest
  still report.

### Where the sniff and PHPMD differ

The sniff is not a drop-in match for PHPMD. Both tools agree on *which names*
are unused — verified name for name over
`tests/fixtures/VariableAnalysisSniff/unused-locals.php`, where PHPMD 2.15.0
and `phpcs --standard=rules.xml` report the same ten lines and nothing else.
They part company on how many times to say so.

| Shape | PHPMD 2.15.0 | This ruleset |
|---|---|---|
| A name assigned once and never read | flags once | flags once |
| A name assigned twice and never read | flags the first assignment | **flags both** |
| A closure local unused inside its own closure | silent | **flags** |
| `allow-unused-foreach-variables` set to `true` | silences the key and both value forms | **silences only the `key => value` value** |
| A name whose only read computes its own next value | silent | silent |

Notes on the three divergences:

- **Every assignment, not every name.** The sniff reports one violation per
  assignment to an unused name; PHPMD reports the name once, at its first
  assignment. The sniff is more useful here — each assignment is its own dead
  store, and a reader fixing only the first would still be left with the
  second. Pinned by
  `tests/fixtures/VariableAnalysisSniff/unused-locals-divergences.php`.
- **Closure scoping.** A closure body is its own scope to the sniff, so a name
  assigned in a closure and read outside it is unused at the assignment *and*
  undefined at the read. PHPMD folds a closure's assignments into the method
  that declares it and reports neither. This is the same divergence already
  recorded for `UndefinedVariable`, seen from the other side; pinned by
  `tests/fixtures/VariableAnalysisSniff/divergences.php`.
- **The `foreach` property, when flipped.** At the shipped default (`false`)
  the two tools are identical — key, value, and associative value are all
  flagged. They only diverge if a consuming project overrides it: PHPMD's
  property skips any name whose parent is a `ForeachStatement`, while the
  sniff's guard reads `isForeachLoopAssociativeValue` and so covers the `key
  => value` value alone. Pinned by a test that flips the property and asserts
  the two survivors.

None of this is tunable — no sniff property changes report granularity or
closure scoping — so the gaps are documented rather than weakened away, per the
issue's own fallback clause. The extra reports are real defects, so adopting
this ruleset can surface findings a previous `phpmd` run did not.

Verified by running both tools over the same fixtures — PHPMD 2.15.0 with a
ruleset enabling only `rulesets/unusedcode.xml/UnusedLocalVariable`, and
`phpcs --standard=rules.xml`.

Ruleset-integration tests covering compliant code, per-line and per-column
violation reporting, the error severity, the absence of a fixer, all five
properties, and the divergences above live at
`tests/Ruleset/UnusedLocalVariableTest.php`. The sniff is also in the generic
three-fixture sweep in `tests/Contract/SniffContractTest.php`.

Its fixtures sit under `tests/fixtures/VariableAnalysisSniff/`, shared with
`UndefinedVariable`: `passing.php` for code both rules must stay silent on,
plus `unused-locals.php` for this rule's parity set and
`unused-locals-divergences.php` for the shapes that belong to neither. There is
no `autofixed.php`, because the rule is not fixable — a test runs the real
fixer over `unused-locals.php` and asserts its output is byte-identical to the
input, so "unfixable" is measured rather than assumed.

## Overlap with #29 (No Dead Code)

Distinct, and no consolidation is needed. This rule's scope is a **local
variable inside a function or method** that is assigned and never read.
[#29](https://github.com/mike-bronner/phpcs-rules/issues/29) covers unused
*declarations* — imports, private class members, and parameters — which are
different artifacts reported by different sniffs. The one shape that could have
been claimed by both, an unused formal parameter, is excluded here on purpose:
it belongs to PHPMD's own separate `UnusedFormalParameter` rule
([#120](https://github.com/mike-bronner/phpcs-rules/issues/120)).

## What remains code review

**An assignment whose only read computes its own next value.**

```php
$counter = 1;
$counter = $counter + 1;   // nothing ever reads the result

return 'result';
```

Both tools count the read on the right-hand side as a use, so both stay silent.
Catching it needs dead-store analysis, which is beyond both. Replacing `phpmd`
with this ruleset loses no coverage here — PHPMD never caught it either — but
the shape is a real defect and needs a reader.

Everything else this rule covers is machine-enforced, and `phpmd` no longer
needs to run separately for it.
