# PHPMD Design: ExitExpression

## Rule

- Do not use `exit` or `die` inside a function or method.

**Why:** an exit-expression in regular code is untestable — nothing can call the
function and observe what it did, because the process is gone. Return a value or
throw instead, and keep process termination in the startup script that knows
what error code the calling environment expects.

```php
// PHPMD (and this ruleset) flags this:
if ($param === 42) {
    exit(23);
}
```

_Source: [phpmd.org/rules/design.html](https://phpmd.org/rules/design.html)
(PHPMD Design ruleset, since PHPMD 0.2)_

## Mapping — Tier 2 (custom sniff)

| PHPMD rule | PHPCS replacement |
| --- | --- |
| `Design/ExitExpression` | `CleanCode.ControlStructures.DisallowExitExpression` (message code `.Found`) |

Enforced by a custom sniff, shipped in the CleanCode standard and therefore
active through the master ruleset (`rules.xml`) — running `phpcs` with
`rules.xml` covers this rule, and `phpmd` does not have to run separately for
it.

### Existing sniffs were evaluated first — none match

| Candidate | Verdict |
| --- | --- |
| `Generic.PHP.ForbiddenFunctions` | **Partial.** Configured with `exit` and `die` it does report both constructs at the right lines — its `register()` derives its token list by tokenising the configured names, which yields `T_EXIT`. But it reports them *everywhere*, file scope included. |
| `Squiz.PHP.DiscouragedFunctions` | Same sniff, same partial match — it extends `Generic.PHP.ForbiddenFunctions`. |
| `SlevomatCodingStandard.ControlStructures.LanguageConstructWithParentheses` | No. It governs the *spelling* of `exit(…)`, not whether the construct may be used. |
| Any other `Generic`/`Squiz`/`Slevomat` sniff | No. Nothing else in the installed standards listens for `T_EXIT` as a forbidden construct. |

The gap is not cosmetic. PHPMD reports an exit expression only where it sits
inside a function or method, because the rule's own remedy is to move the exit
to a startup script — and a startup script's `exit` lives at file scope.
A ruleset that flagged file scope too would forbid the fix it recommends, so the
custom sniff replicates PHPMD's scoping instead.

### Behaviour

- **Detection** — the sniff listens for `T_EXIT`, which PHP emits for both
  `exit` and `die` in any spelling (`exit;`, `exit(0);`, `exit($code);`, `die;`,
  `die('message');`, `EXIT;`, `Die(…)`). It reports each occurrence at the
  construct's own line and column, not at the enclosing declaration. PHPMD has
  no threshold or configurable property for this rule, so there is nothing to
  tune.
- **Scoped to function and method bodies** — a violation is raised only where
  the construct sits inside a `function` scope. A closure or arrow function
  nested in one still counts, because it carries the enclosing `T_FUNCTION`
  among its conditions; a closure written at file scope does not, and is left
  alone. This matches PHPMD on every shape tested: named functions, methods,
  trait methods, enum methods, closures and arrow functions inside a method,
  and — silent on all of these — file scope, a top-level `if` or `foreach`, and
  a file-scope closure or arrow function.
- **Not auto-fixable** — matching PHPMD. There is no mechanical rewrite from a
  termination into a return value or an exception; the replacement depends on
  what the caller is supposed to do next.
- **Methods named `exit` or `die` are not flagged** — `$session->die(…)`,
  `$session?->die(…)`, `Session::exit(…)`, and the declarations of such methods
  call or declare a method that happens to carry a reserved word as its name
  (legal since PHP 7.0). The sniff needs no guard for them: PHPCS treats `exit`
  and `die` as context-sensitive keywords and demotes them to `T_STRING` after
  `function`, `::`, and the object operators, so only the construct itself is
  ever `T_EXIT`. The passing fixture pins that.

### Two divergences — this ruleset is stricter

Both are genuine exit expressions inside a method or function that PDepend, the
parser PHPMD reads with, does not model. They are kept rather than suppressed
for parity, on the same grounds as the extra `VariableAnalysis` reports
documented in [`cleancode-undefinedvariable.md`](cleancode-undefinedvariable.md):
the report is true, so silencing it would cost more than the divergence does.

| Shape | PHPMD 2.15.0 | This ruleset |
| --- | --- | --- |
| `exit` in a method of an anonymous class declared at **file scope** | silent | flagged |
| The fully qualified `\exit` / `\die` spelling | silent | flagged |

The anonymous-class half is a parser blind spot rather than a design decision:
the identical anonymous class written *inside* a named method is reported by
both tools. Both shapes are pinned by
`tests/fixtures/DisallowExitExpressionSniff/divergences.php`.

### How the parity above was established

Every claim on this page was measured, not read off PHPMD's documentation:
PHPMD 2.15.0 was run over the sniff's own fixtures
(`phpmd tests/fixtures/DisallowExitExpressionSniff/<fixture>.php text design`)
and its output compared line by line with the sniff's. On `failing.php` the two
agree on all fifteen lines; on `passing.php` both are silent; `divergences.php`
holds the two shapes where they part, and says so in its own header.
