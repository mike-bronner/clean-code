# Conditionals: Avoid Conditionals

## Standard

Avoid conditionals where possible.

**Why:** they increase cyclomatic complexity, which increases mental debt. Every
branch is another path the reader must hold in their head; code that replaces a
conditional with polymorphism, a mapping array, or a `match` is cheaper to
reason about.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (partial: warning-level sniff + one auto-fix)

The standard is enforced in two halves, both wired into the master `rules.xml`
([#12](https://github.com/mike-bronner/phpcs-rules/issues/12)):

| Half | Rule | Severity | Fixable |
|---|---|---|---|
| Detect every branch | `CleanCode.Conditionals.AvoidConditionals` | warning | no |
| Collapse the boolean-return `if` | `SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn` | warning | yes |

Neither half decides whether a given conditional was *avoidable* — that
judgement stays with code review. What they do is make every branch visible and
countable, and mechanically remove the one shape that is never worth keeping.

### Detection — one warning per branch

The custom `CleanCode.Conditionals.AvoidConditionals` sniff reports a
**warning** for each of four constructs:

| Construct | Violation code |
|---|---|
| `if` (including the `if` of an `else if`) | `IfStatement` |
| `elseif` | `ElseIfStatement` |
| ternary `?:`, including the short form | `TernaryExpression` |
| `switch` | `SwitchStatement` |

Brace-less and alternative-syntax forms (`if:`/`endif`, `switch:`/`endswitch`)
are the same tokens, so they are covered identically.

Warning rather than error is deliberate. "Avoid where possible" is advice, and
advice must not fail a consumer's build; the warning is a prompt to look for a
better construct, not a defect report.

The following are deliberately **not** reported:

- **`else`** — it introduces no new condition. The standard's own rationale is
  cyclomatic complexity, and that metric counts `if`/`elseif`/`case`, never the
  `else`. Reporting it would double-count one decision.
- **`case` / `default`** — a `switch` is reported once, at the keyword, because
  the remedy (replace the whole construct with a mapping array, `match`, or
  polymorphism) is a single action rather than one per arm.
- **`match`** — the recommended *replacement* for a branching `switch` or `if`
  chain, not a conditional to avoid.
- **`??`, `??=`, `?->`** — null-default and null-safe operators collapse a
  branch into an expression instead of adding one, and this ruleset already
  pushes code toward them.
- **`?string` nullable type hints** — punctuation, not a branch.
- **loops (`while`, `for`, `foreach`, `do`) and `catch`** — iteration and error
  handling, each covered by its own standard.

### Auto-fix — the boolean-return `if`

One shape has a rewrite that is safe to apply mechanically:

```php
if ($amount > 100) {
    return true;
}

return false;
```

which is just `return $amount > 100;`. The `if`/`else` variant collapses the
same way, negated when the `if` branch returns `false`.

`SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn`
implements exactly this fixer, including the guard the standard needs: it marks
the violation fixable **only when the condition already evaluates to a
boolean**. Given

```php
public function hasName(string $name): bool
{
    if ($name) {
        return true;
    }

    return false;
}
```

collapsing to `return $name;` would widen the declared `bool` return to
`string`, so the sniff reports the violation and leaves the code alone. That is
the general rule for this standard: **forms without a safe mechanical rewrite
are warned only, never auto-fixed.**

The rule is wired in with `<type>warning</type>`, lowering it from Slevomat's
default error so the whole standard speaks at one severity. `phpcbf` still
applies the fix — fixability is independent of message type. Applying it
deletes the `if`, so the `AvoidConditionals` warning on that line disappears
with it.

### Why a custom sniff for the detection half

No existing PHPCS or Slevomat sniff reports conditionals as such. The
catalogues police the *shape* of a conditional, never its presence:
[`Slevomat ControlStructures.RequireTernaryOperator`](https://github.com/slevomat/coding-standard/blob/master/doc/control-structures.md)
and `DisallowShortTernaryOperator` prescribe which form to use,
`Generic.CodeAnalysis.UnconditionalIfStatement` catches an `if` on a constant,
and `Squiz.PHP.DisallowInlineIf` targets ternaries alone. Evaluated against
this standard's test suite, none of them matches it. The detection half is
therefore a custom sniff; the auto-fix half is not, because Slevomat's
`UselessIfConditionWithReturn` already implements it correctly and a
reimplementation would only add a second fixer to keep in step.

## Related complexity rules

This standard is the umbrella discipline; the complexity-metric slice it
anticipates ("not statically enforceable beyond a complexity-metric warning")
is tracked separately:

- [#88 — PHPMD/CodeSize: CyclomaticComplexity](https://github.com/mike-bronner/phpcs-rules/issues/88)
- [#89 — PHPMD/CodeSize: NPathComplexity](https://github.com/mike-bronner/phpcs-rules/issues/89)
- [#87 — PHPMD/CodeSize: ExcessiveClassComplexity](https://github.com/mike-bronner/phpcs-rules/issues/87)
- [#36 — Indentation: Methods (max 2 nesting levels)](https://github.com/mike-bronner/phpcs-rules/issues/36)

Specific *shapes* of avoidable conditionals are likewise catalogued and
enforced as their own standards — no `else`/`elseif`, mapping arrays over
branching, combining conditionals — each on its own terms.

## What remains code review

The sniff counts branches; it cannot judge them. These stay with the reviewer:

- **Whether a better construct existed.** Replacing a branch with polymorphism
  needs a type to dispatch on, and replacing it with a mapping array needs the
  arms to be values rather than statements. Only a reader knows which applies.
- **Guard clauses.** An early-return guard is warned like any other `if`, and
  is often the right code anyway. The warning asks the question; it does not
  answer it.
- **Every conditional other than the boolean-return `if`.** No mechanical
  rewrite exists for them, so `phpcbf` leaves them untouched by design.
- **Warning fatigue.** A codebase adopting this ruleset will see one warning per
  branch. That volume is the point — it is a complexity budget made visible —
  but acting on it is a review decision, not a lint failure.
