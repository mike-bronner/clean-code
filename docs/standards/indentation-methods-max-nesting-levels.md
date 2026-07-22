# Indentation: Methods (max 2 nesting levels)

## Standard

- Methods should have no more than **2 levels of nesting**.

**Why:** deep nesting often signals different concepts or concerns living in one
method and is a prompt to refactor — it reduces code complexity (mental debt)
and keeps concerns separated.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

Enforced by **`CleanCode.Metrics.MethodNestingLevel`**, a custom sniff with a
single error code:

- **`MaxExceeded`** — a control structure nested more than 2 levels deep inside
  a function/method body. Each offending control structure is reported at its
  own line, so every excess nesting level is flagged individually; the message
  states the actual level found.

### How nesting is counted

One level is added per control structure — `if`/`elseif`/`else`, loops
(`for`/`foreach`/`while`/`do`), `switch`/`match`, `try`/`catch`/`finally`, and
closures. Two structural details keep the count faithful to how a reader sees
indentation:

- **`case`/`default` do not add a level** — they belong to the enclosing
  `switch`, so an `if` inside a `case` is level 2, not level 3.
- **`elseif`/`else`/`catch`/`finally` sit at the same level** as the
  `if`/`try` they continue, rather than nesting beneath it — an `if … else`
  chain is one level, and statements inside `else` or `catch` are counted at
  the correct depth.

A closure inside a method still counts as a nesting level. The rule applies
only inside a function/method body; top-level script code is out of scope.

## Why not an existing sniff

The nearest candidate, **`Generic.Metrics.NestingLevel`**, was evaluated and
does not match this standard:

- It reports **once per function**, at the function-declaration line, with only
  the *maximum* depth reached — this standard requires each excess control
  structure flagged at **its own line**.
- It counts by raw brace depth (`token['level']`) rather than the clean-code set
  of control structures above, so its notion of a "level" differs (e.g. it does
  not treat `case` or an `if…else` chain the way this standard does).

Its thresholds are configurable, but no configuration produces per-line reports
over the required token set, so bending it would mean weakening the tests. A
custom sniff enforces the rule exactly instead.

## Not auto-fixable

Reducing nesting requires a semantic refactor — extracting a method, inverting a
condition, or introducing a guard clause — that a token rewriter cannot apply
safely without changing behaviour. The sniff therefore reports but does not fix;
the refactor stays with the developer.
